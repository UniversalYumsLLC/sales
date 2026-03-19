Good. I now have a thorough understanding of all files. Here's my review:

---

## Deep Code Review: Test Backfill PR

### Critical (must fix before merge)

| Finding | File:Line | Fix |
| ------- | --------- | --- |
| `InvoiceFactory` uses `LocalCustomerMetadata::factory()` for `fulfil_party_id`, but Laravel will pass the **model instance**, not the `fulfil_party_id` value. Since `LocalCustomerMetadata` uses a non-standard primary key (`fulfil_party_id`, non-incrementing), the factory will insert the model object, not the integer ID. This will cause `Invoice::factory()->create()` to fail or insert bad data when no explicit `fulfil_party_id` is passed. | `database/factories/InvoiceFactory.php:24` | Change to a callback: `'fulfil_party_id' => fn () => LocalCustomerMetadata::factory()->create()->fulfil_party_id` or use `fake()->unique()->randomNumber(5)` and handle the parent creation separately. Verify by running `Invoice::factory()->create()` in isolation. |
| `EmailRecordFactory` references `EmailRecord::TYPE_INITIAL_INVOICE` but the constant is defined on `EmailRecord` — checking source confirms this exists. However, the factory uses `Invoice::factory()` for `invoice_id`, which chains to `LocalCustomerMetadata::factory()` via the issue above. If the InvoiceFactory issue is real, this factory also breaks. | `database/factories/EmailRecordFactory.php:23` | Fix cascades from fixing InvoiceFactory. |

### High (fix soon)

| Finding | File:Line | Fix |
| ------- | --------- | --- |
| `InvoicePdfControllerTest` writes to `/tmp/test.pdf` — a shared system path. Parallel test runs or concurrent CI jobs could collide. Also, if the assertion fails before `@unlink`, the file leaks. | `tests/Feature/InvoicePdfControllerTest.php:28-33` | Use `tempnam(sys_get_temp_dir(), 'test_')` for a unique path, and put cleanup in `afterEach` or use a try/finally block. |
| `TestModeServiceTest::isEnabled` sets `ar_test_mode` to `true` after `false`, then manually forgets the cache key. This is testing cache implementation details. If the `Setting::set()` method already clears cache (it does — line 57 of Setting.php), the `Cache::forget` is unnecessary and misleading — it suggests the service has a cache bug. | `tests/Unit/Services/TestModeServiceTest.php:81` | Remove the `Cache::forget` line. `Setting::set()` already clears cache. If the test still fails without it, that reveals an actual bug worth investigating. |
| `ArAutomationServiceTest` tests protected methods via Reflection. These methods have significant business logic (overdue follow-up timing), so testing them is worthwhile, but this couples tests to internal implementation. If the methods are refactored or renamed, tests break despite behavior being unchanged. | `tests/Unit/Services/ArAutomationServiceTest.php:40+` | Acceptable for now since these are critical business rules. Add a comment noting these test protected methods intentionally. Consider extracting to a dedicated class (e.g., `OverdueFollowupPolicy`) in a future refactor if more rules are added. |
| `SocialAuthTest` uses `Socialite::shouldReceive('driver')` but doesn't mock the `stateless()` or `user()` chain properly — it mocks `Provider::class` and calls `shouldReceive('user')`. Looking at the controller, it calls `Socialite::driver('google')->user()` directly. The mock works because Mockery intercepts the method chain, but if the controller changes to add `.stateless()` before `.user()`, these tests will silently pass with incorrect behavior. | `tests/Feature/Auth/SocialAuthTest.php:35-37` | This is fine for current implementation but fragile. Consider adding `$provider->shouldReceive('stateless')->andReturnSelf()` defensively if that method exists on the provider. |

### Medium (improve)

| Finding | File:Line | Fix |
| ------- | --------- | --- |
| `ActiveCustomersControllerTest` `beforeEach` mocks FulfilService for all tests with overly broad `shouldReceive` calls. Tests that don't use all these mock returns (e.g., auth redirect tests) still set them up. This adds noise and hides which endpoints actually call which FulfilService methods. | `tests/Feature/ActiveCustomersControllerTest.php:9-29` | Consider moving the mock setup into a helper function or trait, and only applying it in tests that actually hit the controller logic. The auth redirect tests don't need the mock at all. |
| `ProspectControllerTest` line 134-137 is a permanently skipped test with a comment about SQLite. Skipped tests that will never run in CI are dead code. | `tests/Feature/ProspectControllerTest.php:133-137` | Either remove the test entirely, or use a conditional skip: `if (DB::getDriverName() === 'sqlite') { $this->markTestSkipped(...); }` so it runs in MySQL CI environments. |
| `AdminRoutesTest` repeats the same pattern 12 times for 4 routes × 3 roles. This is a good candidate for a dataset to reduce duplication and make it easier to add new admin routes. | `tests/Feature/AdminRoutesTest.php:1-113` | Consider using Pest's `dataset()` or `with()` to parameterize the route/role combinations. |
| `InvoiceTest::syncFromFulfil` creates a `LocalCustomerMetadata` manually before calling `syncFromFulfil`, but `syncFromFulfil` itself calls `LocalCustomerMetadata::findOrCreateForCustomer()`. The test creates metadata with `fulfil_party_id => 12345` then the source code also creates one — potentially hitting a unique constraint or no-op depending on timing. This works but is redundant and could mask issues. | `tests/Unit/Models/InvoiceTest.php:7-8` | The manual metadata creation is necessary because the test uses in-memory SQLite which doesn't support the full factory chain. Fine as-is, but add a comment explaining why. |
| `GmailControllerTest` "sync requires gmail connection" test asserts redirect but doesn't check the error message or session flash. A redirect to `gmail.index` could happen for multiple reasons. | `tests/Feature/GmailControllerTest.php:80-90` | Add `->assertSessionHas('error')` or similar to verify the redirect reason. |

### Low (suggestions)

| Finding | File:Line | Fix |
| ------- | --------- | --- |
| `EmailTemplateFactory` always creates a template with `key => TYPE_INITIAL_INVOICE`. If multiple templates are created in a single test, this will violate uniqueness if `key` has a unique index. | `database/factories/EmailTemplateFactory.php:21` | Use `fake()->randomElement(EmailTemplate::getValidKeys())` or cycle through keys to avoid collisions. |
| `ProspectFactory` includes `'broker' => false` in the default state, but looking at the `prospects` table `fillable`, `broker` is a valid field. The factory omits `discount_percent`, `payment_terms`, `shipping_terms`, `shelf_life_requirement`, and `vendor_guide` — all nullable, so that's fine, but `broker_commission` and `broker_company_name` are also omitted. | `database/factories/ProspectFactory.php:21-28` | Fine as-is since the omitted fields are nullable. No action needed. |
| `CustomerSkuFactory` uses `LocalCustomerMetadata::factory()` for `fulfil_party_id` — same potential issue as `InvoiceFactory` above regarding factory resolution with non-standard primary keys. | `database/factories/CustomerSkuFactory.php:22` | Same fix as InvoiceFactory if confirmed broken. |
| `SocialAuthTest` line 111 hardcodes `eli@universalyums.com` knowledge — the controller has special casing for this email to auto-assign admin. No test covers this code path. | `app/Http/Controllers/Auth/SocialAuthController.php:111` | Consider adding a test for the `eli@universalyums.com` admin auto-assignment. |

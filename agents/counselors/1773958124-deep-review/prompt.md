# Code Review

## Changes

This PR backfills test coverage for a Laravel sales application. The changes include:
- Adding `HasFactory` trait to 8 models (CustomerSku, Email, EmailRecord, EmailTemplate, Invoice, LocalCustomerMetadata, UserGmailToken, UserInvite)
- Creating 10 new model factories
- Updating UserFactory with role states (admin, salesperson)
- Adding Fulfil environment variables to phpunit.xml for test isolation
- Creating 12 new test files covering auth, controllers, models, and services

## Full Diff

```diff
diff --git a/app/Models/CustomerSku.php b/app/Models/CustomerSku.php
--- a/app/Models/CustomerSku.php
+++ b/app/Models/CustomerSku.php
@@ -2,12 +2,15 @@
+use Illuminate\Database\Eloquent\Factories\HasFactory;
 class CustomerSku extends Model
 {
+    use HasFactory;
(same pattern for Email, EmailRecord, EmailTemplate, Invoice, LocalCustomerMetadata, UserGmailToken, UserInvite)

diff --git a/database/factories/UserFactory.php
--- added role field, google_id, admin() and salesperson() states

diff --git a/database/factories/ProspectFactory.php (new)
--- factory with company_name, status, created_by, company_urls, broker fields + status states

diff --git a/database/factories/ProspectContactFactory.php (new)
--- factory with prospect_id, type, name, value + type states (buyer, accountsPayable, uncategorized, broker)

diff --git a/database/factories/InvoiceFactory.php (new)
--- factory with fulfil_id, number, fulfil_party_id (via LocalCustomerMetadata::factory), due_date, etc + overdue/paid states

diff --git a/database/factories/EmailRecordFactory.php (new)
--- factory with fulfil_party_id, invoice_id, email_type, sent_at, pdf_path

diff --git a/database/factories/EmailTemplateFactory.php (new)
--- factory with key, name, subject, body

diff --git a/database/factories/LocalCustomerMetadataFactory.php (new)
--- factory with fulfil_party_id, company_urls, customer_type, broker + distributor state

diff --git a/database/factories/EmailFactory.php (new)
--- factory with user_id, gmail fields, direction, from/to, subject, body, email_date + outbound state

diff --git a/database/factories/CustomerSkuFactory.php (new)
--- factory with fulfil_party_id, yums_sku, customer_sku

diff --git a/database/factories/UserGmailTokenFactory.php (new)
--- factory with user_id, gmail_email, access_token, refresh_token, token_expires_at + expired state

diff --git a/database/factories/UserInviteFactory.php (new)
--- factory with email, role, invited_by

diff --git a/phpunit.xml
--- Added FULFIL_DEFAULT_ENV, FULFIL_SANDBOX_SUBDOMAIN, FULFIL_SANDBOX_TOKEN, FULFIL_PRODUCTION_SUBDOMAIN, FULFIL_PRODUCTION_TOKEN env vars

diff --git a/tests/Unit/Models/UserTest.php (new)
--- Tests: isAdmin, isSalesperson, canAccessGmailIntegration, hasRole, hasGmailConnected, getRoles

diff --git a/tests/Unit/Models/InvoiceTest.php (new)
--- Tests: syncFromFulfil create/update, isOverdue scenarios, daysUntilDue scenarios

diff --git a/tests/Unit/Models/ProspectTest.php (new)
--- Tests: getEmailDomains, matchesEmailDomain, status constants, scopeNotDormant, addCompanyUrl

diff --git a/tests/Unit/Services/ArAutomationServiceTest.php (new)
--- Tests: shouldSendOverdueFollowup timing logic, wasInvoiceModifiedSinceLastEmail

diff --git a/tests/Unit/Services/TestModeServiceTest.php (new)
--- Tests: filterEmails in test/production mode, getFulfilEnvironment, canSendEmailTo, isEnabled

diff --git a/tests/Feature/Auth/SocialAuthTest.php (new)
--- Tests: domain rejection, allowed domain, new user creation with/without invite, existing user update, provider validation

diff --git a/tests/Feature/ActiveCustomersControllerTest.php (new)
--- Tests: index/show load, auth redirect, company URL update, contact CRUD, broker settings, customer type, categorization, AR settings

diff --git a/tests/Feature/ProspectControllerTest.php (new)
--- Tests: index/create/show load, store with contacts, domain extraction, update, status transitions, categorization, validation

diff --git a/tests/Feature/AccountsReceivableControllerTest.php (new)
--- Tests: index load, auth redirect, data passing

diff --git a/tests/Feature/GmailControllerTest.php (new)
--- Tests: role-based access (salesperson/admin/user), connect, disconnect, fullSyncAll admin check, backfill domains admin check

diff --git a/tests/Feature/AdminRoutesTest.php (new)
--- Tests: admin access to all admin routes, 403 for salesperson/user on all admin routes, unauthenticated redirect

diff --git a/tests/Feature/InvoicePdfControllerTest.php (new)
--- Tests: auth requirements, download/regenerate, resend email validation
```

## Key File Contents

### tests/Unit/Services/ArAutomationServiceTest.php
```php
<?php
use App\Models\EmailRecord;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Models\LocalCustomerMetadata;
use App\Services\ArAutomationService;
use App\Services\ArEmailService;
use App\Services\FulfilService;
use App\Services\InvoicePdfService;
use App\Services\TestModeService;

function makeArAutomationService(
    ?FulfilService $fulfil = null,
    ?ArEmailService $emailService = null,
    ?InvoicePdfService $pdfService = null,
    ?TestModeService $testMode = null,
): ArAutomationService {
    return new ArAutomationService(
        $fulfil ?? Mockery::mock(FulfilService::class),
        $emailService ?? Mockery::mock(ArEmailService::class),
        $pdfService ?? Mockery::mock(InvoicePdfService::class),
        $testMode ?? Mockery::mock(TestModeService::class)->shouldReceive('isEnabled')->andReturn(false)->getMock(),
    );
}

// Tests use ReflectionMethod to test protected methods shouldSendOverdueFollowup and wasInvoiceModifiedSinceLastEmail
```

### tests/Feature/Auth/SocialAuthTest.php
```php
<?php
// Uses mockSocialiteUser() helper function to create SocialiteUser instances
// Mocks Socialite::driver('google') with Mockery
// Tests domain restriction, invite flow, existing user update
```

### tests/Feature/ActiveCustomersControllerTest.php
```php
<?php
// Uses beforeEach to mock FulfilService globally for all tests in this file
// Tests CRUD operations on contacts, broker settings, customer type
// Tests AR settings update
```

### database/factories/InvoiceFactory.php
```php
<?php
// fulfil_party_id => LocalCustomerMetadata::factory() - creates related factory
```

### database/factories/UserGmailTokenFactory.php
```php
<?php
// access_token and refresh_token use fake()->sha256() - model has 'encrypted' cast
```

## Project Guidelines

From CLAUDE.md:
- Always use `use` statements at top of PHP files
- Use PHP 8 constructor property promotion
- Always use explicit return type declarations
- Use appropriate PHP type hints
- Prefer PHPDoc blocks over inline comments
- Run `vendor/bin/pint --dirty` before finalizing
- Every change should be tested
- Use `php artisan make:*` commands for scaffolding
- Use Pest-style tests (it(), test())
- MySQL index name limit: 64 chars
- Use Eloquent relationships over raw queries
- Mock external services (FulfilService, GmailService)

## Review Instructions

You are performing a deep code review of test backfill changes. Be critical, direct, and thorough.

For each changed file, analyze:

### 1. Correctness & Logic
- Are tests actually testing what they claim?
- Are assertions sufficient or too weak?
- Missing edge cases in test scenarios
- Factory definitions matching model schemas

### 2. Error Handling
- Tests that could produce false positives/negatives
- Missing error scenario tests

### 3. Security
- Test credentials or tokens that might leak
- phpunit.xml env vars safety

### 4. Performance
- Factory chains creating unnecessary related models
- Tests that could be unit tests but are feature tests

### 5. Architecture & Boundaries
- Tests reaching into protected methods via Reflection
- Mock setup patterns - too broad or too narrow?
- beforeEach mocking covering too much

### 6. State & Data Integrity
- Factory definitions with realistic data
- Factory relationships matching actual DB constraints

### 7. Code Quality
- Consistent test naming
- Helper function patterns
- Dead code or commented-out code

### 8. Compliance with Project Guidelines
- Pest-style test patterns
- Use statements
- PHPDoc blocks where appropriate

## Output Format

Structure your response as:

### Critical (must fix before merge)

| Finding | File:Line | Fix |
| ------- | --------- | --- |

### High (fix soon)

| Finding | File:Line | Fix |
| ------- | --------- | --- |

### Medium (improve)

| Finding | File:Line | Fix |
| ------- | --------- | --- |

### Low (suggestions)

| Finding | File:Line | Fix |
| ------- | --------- | --- |

Be specific with file:line references and concrete fix descriptions.
Do NOT flag things that are fine. Only report actual issues.

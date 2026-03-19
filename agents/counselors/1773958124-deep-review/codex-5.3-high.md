### Critical (must fix before merge)

| Finding | File:Line | Fix |
| ------- | --------- | --- |
| None identified. | - | - |

### High (fix soon)

| Finding | File:Line | Fix |
| ------- | --------- | --- |
| `EmailRecordFactory` default state is internally inconsistent: `fulfil_party_id` is random while `invoice_id` creates a different customer context. This can violate the `email_records.fulfil_party_id` FK or create mismatched invoice/customer data. | [database/factories/EmailRecordFactory.php:22](/Users/jkudish/Herd/uy-sales/.claude/worktrees/agent-ab4bec40/database/factories/EmailRecordFactory.php:22) | Make `invoice_id` and `fulfil_party_id` derive from the same `LocalCustomerMetadata` record (single source of truth via state/afterMaking). |
| `EmailTemplateFactory` always sets `key = initial_invoice`, but `email_templates.key` is unique, so creating more than one record from default factory will fail. | [database/factories/EmailTemplateFactory.php:21](/Users/jkudish/Herd/uy-sales/.claude/worktrees/agent-ab4bec40/database/factories/EmailTemplateFactory.php:21) | Use a unique default key, and add explicit named states for canonical template keys when deterministic values are needed. |

### Medium (improve)

| Finding | File:Line | Fix |
| ------- | --------- | --- |
| Test is unconditionally skipped, so contact categorization flow has no coverage despite being listed as covered. | [tests/Feature/ProspectControllerTest.php:133](/Users/jkudish/Herd/uy-sales/.claude/worktrees/agent-ab4bec40/tests/Feature/ProspectControllerTest.php:133) | Align SQLite test schema with runtime allowed types (or run this test in MySQL CI) and remove unconditional skip. |
| Test name says token is removed, but it only asserts redirect and a mocked method call; token deletion is never verified. | [tests/Feature/GmailControllerTest.php:51](/Users/jkudish/Herd/uy-sales/.claude/worktrees/agent-ab4bec40/tests/Feature/GmailControllerTest.php:51) | Use real service behavior with `Http::fake()` and assert DB side effect (`UserGmailToken` deleted). |
| Comment says “No methods should be called,” but mock has no `shouldNotReceive` expectations, so this can false-pass. | [tests/Feature/GmailControllerTest.php:83](/Users/jkudish/Herd/uy-sales/.claude/worktrees/agent-ab4bec40/tests/Feature/GmailControllerTest.php:83) | Add explicit negative expectations (`shouldNotReceive`) for sync-related methods and assert the error flash message. |

### Low (suggestions)

| Finding | File:Line | Fix |
| ------- | --------- | --- |
| AR feature assertion is too weak (`has('totals')` only), so incorrect calculations/order can still pass. | [tests/Feature/AccountsReceivableControllerTest.php:50](/Users/jkudish/Herd/uy-sales/.claude/worktrees/agent-ab4bec40/tests/Feature/AccountsReceivableControllerTest.php:50) | Assert concrete totals and at least one transformed invoice field (`days_overdue`, sorted order). |
| Unit tests use `ReflectionMethod` on protected methods, coupling tests to implementation details rather than behavior contracts. | [tests/Unit/Services/ArAutomationServiceTest.php:40](/Users/jkudish/Herd/uy-sales/.claude/worktrees/agent-ab4bec40/tests/Unit/Services/ArAutomationServiceTest.php:40) | Add tests through public `processInvoice`/`processAllInvoices` paths with mocked dependencies and email-record fixtures. |


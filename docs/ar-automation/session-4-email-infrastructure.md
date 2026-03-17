# AR Automation - Session 4: Email Infrastructure

## Overview

This session implements the email infrastructure for AR Automation, including email templates, the template settings UI for admins, and Test Mode functionality. This creates the foundation that the automation system (Session 5) will use to send emails.

## Prerequisites

- **Session 1: Data Foundation** must be completed first
  - `email_records` table must exist
  - EmailRecord model must exist

## Scope

1. Email template model and database storage
2. Email Template Settings admin page
3. Email sending service
4. Test Mode implementation
5. Email record tracking

---

## 1. Email Templates

### 1.1 Template Types

The system supports 8 email template types:

| Template Key | Description | Recipient |
|--------------|-------------|-----------|
| `sku_mapping_error` | Internal alert when SKU mapping is missing | accountsreceivable@universalyums.com |
| `initial_invoice` | First invoice sent to customer | Customer AP contacts |
| `initial_invoice_ap_portal` | First invoice notification for portal customers | accountsreceivable@universalyums.com |
| `invoice_modified` | Invoice was modified after initial send | Customer AP contacts |
| `invoice_modified_ap_portal` | Modified invoice for portal customers | accountsreceivable@universalyums.com |
| `due_reminder` | 7 days before due date | Customer AP contacts |
| `overdue_notification` | 1 day after due date | Customer AP contacts |
| `overdue_followup` | Weekly follow-up for overdue invoices | Customer AP contacts |

### 1.2 Template Fields

Each template has:

| Field | Type | Description |
|-------|------|-------------|
| key | string | Unique identifier (from list above) |
| name | string | Display name for admin UI |
| subject | text | Email subject line (user-configurable) |
| body | text | Email body content (user-configurable) |
| from_email | string | Sender email (hardcoded: accountsreceivable@universalyums.com) |

### 1.3 Database Schema: `email_templates`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint (PK) | Auto-increment |
| key | string (unique) | Template identifier |
| name | string | Human-readable name |
| subject | text | Email subject |
| body | text | Email body (supports basic formatting) |
| created_at | timestamp | Laravel timestamp |
| updated_at | timestamp | Laravel timestamp |

### 1.4 Template Variable Placeholders

Templates should support variable placeholders that get replaced when sending:

| Placeholder | Description | Available In |
|-------------|-------------|--------------|
| `{{customer_name}}` | Customer company name | All templates |
| `{{invoice_number}}` | Invoice number | All except sku_mapping_error |
| `{{invoice_date}}` | Invoice date | All except sku_mapping_error |
| `{{due_date}}` | Invoice due date | All except sku_mapping_error |
| `{{total_amount}}` | Invoice total | All except sku_mapping_error |
| `{{balance_due}}` | Remaining balance | All except sku_mapping_error |
| `{{ap_portal_url}}` | Customer's AP portal URL | AP Portal templates |
| `{{missing_skus}}` | List of unmapped SKUs | sku_mapping_error |

---

## 2. Email Template Settings Page

### 2.1 Access Control

- **Admin users only** - The page should only be accessible to users with type "Admin"
- Add link in user profile dropdown menu (visible only to admins)
- Route: `/admin/email-templates` (or similar)

### 2.2 UI Layout

```
Email Template Settings
========================

[Template Selector Dropdown: Select a template to edit...]

------------------------------------------------------------

Template: Initial Invoice
--------------------------

Subject:
+--------------------------------------------------+
| Invoice {{invoice_number}} from Universal Yums   |
+--------------------------------------------------+

Body:
+--------------------------------------------------+
| Dear {{customer_name}},                          |
|                                                  |
| Please find attached invoice {{invoice_number}} |
| dated {{invoice_date}}.                          |
|                                                  |
| Total Amount: {{total_amount}}                   |
| Due Date: {{due_date}}                           |
|                                                  |
| Thank you for your business.                     |
|                                                  |
| Universal Yums Accounts Receivable               |
+--------------------------------------------------+

Available Placeholders:
{{customer_name}}, {{invoice_number}}, {{invoice_date}},
{{due_date}}, {{total_amount}}, {{balance_due}}

                                        [Save Template]
```

### 2.3 Features

- Dropdown to select which template to edit
- Text input for subject line
- Textarea for body (consider rich text editor for formatting)
- Display available placeholders for the selected template
- Save button with success/error feedback
- Preview button (optional but helpful)

---

## 3. Email Sending Service

### 3.1 ArEmailService

Create a service class to handle AR email sending.

```php
class ArEmailService
{
    /**
     * Send an AR email
     *
     * @param string $templateKey Template to use
     * @param Customer $customer Customer record
     * @param Invoice|null $invoice Invoice record (if applicable)
     * @param string|null $pdfPath Path to PDF attachment
     * @return bool Success status
     */
    public function send(
        string $templateKey,
        Customer $customer,
        ?Invoice $invoice = null,
        ?string $pdfPath = null
    ): bool;

    /**
     * Get recipients for a template type
     */
    protected function getRecipients(string $templateKey, Customer $customer): array;

    /**
     * Replace placeholders in template
     */
    protected function replacePlaceholders(
        string $content,
        Customer $customer,
        ?Invoice $invoice
    ): string;

    /**
     * Check if email can be sent (Test Mode validation)
     */
    protected function canSendTo(string $email): bool;

    /**
     * Record the sent email
     */
    protected function recordEmail(
        string $templateKey,
        Customer $customer,
        ?Invoice $invoice,
        ?string $pdfPath
    ): void;
}
```

### 3.2 Recipient Logic

| Template | Recipients |
|----------|------------|
| `sku_mapping_error` | accountsreceivable@universalyums.com |
| `initial_invoice_ap_portal` | accountsreceivable@universalyums.com |
| `invoice_modified_ap_portal` | accountsreceivable@universalyums.com |
| All others | Customer's AP contacts (all contacts in single email) |

### 3.3 Email Configuration

- **From**: accountsreceivable@universalyums.com (all emails)
- **Reply-To**: accountsreceivable@universalyums.com
- Use Laravel's Mail facade with configured mail driver

---

## 4. Test Mode

### 4.1 Purpose

Test Mode allows testing AR automation in production without sending real emails to customers.

### 4.2 Setting Storage

Add to application settings (database or config):

| Setting | Type | Default |
|---------|------|---------|
| ar_test_mode | boolean | false |

### 4.3 Admin UI

- Location: Admin settings page or dedicated AR settings section
- Toggle switch: "Enable Test Mode"
- Only visible/accessible to Admin users
- Show warning when enabled: "Test Mode is active. Emails will only be sent to @universalyums.com addresses."

### 4.4 Test Mode Behaviors

When Test Mode is **enabled**:

1. **Fulfil Environment**: Switch from Production to Sandbox
   - All Fulfil API calls should use sandbox credentials/endpoint
   - This affects invoice fetching, metafield syncing, etc.

2. **Email Restriction**: Block emails to non-company addresses
   - Only allow sending to emails ending with `@universalyums.com`
   - If recipient is external, log the attempt but don't send
   - For customer AP contacts, redirect to a test inbox or skip

### 4.5 Implementation

```php
class TestModeService
{
    public function isEnabled(): bool
    {
        return Setting::get('ar_test_mode', false);
    }

    public function canSendEmailTo(string $email): bool
    {
        if (!$this->isEnabled()) {
            return true; // Normal mode, send to anyone
        }

        // Test mode: only @universalyums.com
        return str_ends_with(strtolower($email), '@universalyums.com');
    }

    public function getFulfilEnvironment(): string
    {
        return $this->isEnabled() ? 'sandbox' : 'production';
    }
}
```

### 4.6 Fulfil Environment Switching

Update FulfilService to check Test Mode:

```php
class FulfilService
{
    protected function getBaseUrl(): string
    {
        $testMode = app(TestModeService::class);

        if ($testMode->isEnabled()) {
            return config('fulfil.sandbox_url');
        }

        return config('fulfil.production_url');
    }

    protected function getApiKey(): string
    {
        $testMode = app(TestModeService::class);

        if ($testMode->isEnabled()) {
            return config('fulfil.sandbox_api_key');
        }

        return config('fulfil.production_api_key');
    }
}
```

### 4.7 Config Requirements

Ensure these config values exist:

```php
// config/fulfil.php
return [
    'production_url' => env('FULFIL_PRODUCTION_URL'),
    'production_api_key' => env('FULFIL_PRODUCTION_API_KEY'),
    'sandbox_url' => env('FULFIL_SANDBOX_URL'),
    'sandbox_api_key' => env('FULFIL_SANDBOX_API_KEY'),
];
```

---

## 5. Email Record Tracking

### 5.1 Recording Sent Emails

Every email sent through the AR system should be recorded in `email_records`.

```php
EmailRecord::create([
    'customer_id' => $customer->id,
    'invoice_id' => $invoice?->id,
    'email_type' => $templateKey,
    'sent_at' => now(),
    'pdf_path' => $pdfPath,
]);
```

### 5.2 Querying Email History

The email records will be queried by the automation system to determine:

- Has initial invoice email been sent?
  ```php
  EmailRecord::where('invoice_id', $id)
      ->where('email_type', 'initial_invoice')
      ->exists();
  ```

- When was last overdue follow-up sent?
  ```php
  EmailRecord::where('invoice_id', $id)
      ->where('email_type', 'overdue_followup')
      ->latest('sent_at')
      ->first()?->sent_at;
  ```

---

## 6. Default Template Content

Seed the database with default template content. Users can customize these.

### 6.1 SKU Mapping Error

**Subject**: `SKU Mapping Required - {{customer_name}}`

**Body**:
```
An invoice for {{customer_name}} requires customer SKU mapping, but the following SKUs are not mapped:

{{missing_skus}}

Please add the missing SKU mappings before the invoice can be processed.
```

### 6.2 Initial Invoice

**Subject**: `Invoice {{invoice_number}} from Universal Yums`

**Body**:
```
Dear {{customer_name}},

Please find attached invoice {{invoice_number}} dated {{invoice_date}}.

Invoice Total: {{total_amount}}
Due Date: {{due_date}}

Thank you for your business.

Best regards,
Universal Yums Accounts Receivable
```

### 6.3 Initial Invoice - AP Portal

**Subject**: `ACTION REQUIRED: Upload Invoice {{invoice_number}} to AP Portal`

**Body**:
```
Invoice {{invoice_number}} for {{customer_name}} is ready for upload.

Customer AP Portal: {{ap_portal_url}}

Invoice Total: {{total_amount}}
Due Date: {{due_date}}

Please upload the attached invoice to the customer's AP portal.
```

### 6.4 Invoice Modified

**Subject**: `Updated Invoice {{invoice_number}} from Universal Yums`

**Body**:
```
Dear {{customer_name}},

Invoice {{invoice_number}} has been updated. Please find the revised invoice attached.

Invoice Total: {{total_amount}}
Due Date: {{due_date}}

Please disregard any previous versions of this invoice.

Best regards,
Universal Yums Accounts Receivable
```

### 6.5 Invoice Modified - AP Portal

**Subject**: `ACTION REQUIRED: Re-upload Updated Invoice {{invoice_number}}`

**Body**:
```
Invoice {{invoice_number}} for {{customer_name}} has been modified and needs to be re-uploaded.

Customer AP Portal: {{ap_portal_url}}

Invoice Total: {{total_amount}}
Due Date: {{due_date}}

Please upload the attached updated invoice to replace the previous version.
```

### 6.6 Due Reminder (Due - 7 Days)

**Subject**: `Payment Reminder: Invoice {{invoice_number}} Due Soon`

**Body**:
```
Dear {{customer_name}},

This is a friendly reminder that invoice {{invoice_number}} is due on {{due_date}}.

Balance Due: {{balance_due}}

Please find the invoice attached for your reference.

Best regards,
Universal Yums Accounts Receivable
```

### 6.7 Overdue Notification (Due + 1 Day)

**Subject**: `Past Due: Invoice {{invoice_number}}`

**Body**:
```
Dear {{customer_name}},

Invoice {{invoice_number}} was due on {{due_date}} and is now past due.

Balance Due: {{balance_due}}

Please remit payment at your earliest convenience. If you have already sent payment, please disregard this notice.

Best regards,
Universal Yums Accounts Receivable
```

### 6.8 Overdue Follow-Up (Weekly)

**Subject**: `Payment Required: Invoice {{invoice_number}} Overdue`

**Body**:
```
Dear {{customer_name}},

Our records indicate that invoice {{invoice_number}} remains unpaid. The invoice was due on {{due_date}}.

Balance Due: {{balance_due}}

Please contact us if you have any questions or need to discuss payment arrangements.

Best regards,
Universal Yums Accounts Receivable
```

---

## 7. Database Seeder

Create a seeder to populate default email templates:

```php
class EmailTemplateSeeder extends Seeder
{
    public function run()
    {
        $templates = [
            [
                'key' => 'sku_mapping_error',
                'name' => 'SKU Mapping Error',
                'subject' => 'SKU Mapping Required - {{customer_name}}',
                'body' => '...',
            ],
            // ... all 8 templates
        ];

        foreach ($templates as $template) {
            EmailTemplate::updateOrCreate(
                ['key' => $template['key']],
                $template
            );
        }
    }
}
```

---

## Deliverables Checklist

- [ ] Migration: Create `email_templates` table
- [ ] Model: Create EmailTemplate model
- [ ] Seeder: Create EmailTemplateSeeder with default content
- [ ] UI: Email Template Settings page (admin only)
- [ ] UI: Template editor with subject/body fields
- [ ] UI: Placeholder reference display
- [ ] Service: Create ArEmailService
- [ ] Service: Implement placeholder replacement
- [ ] Service: Implement recipient logic per template type
- [ ] Service: Implement email sending with attachments
- [ ] Service: Implement email recording
- [ ] Migration: Add `ar_test_mode` to settings
- [ ] Service: Create TestModeService
- [ ] UI: Test Mode toggle (admin only)
- [ ] Integration: Update FulfilService for environment switching
- [ ] Config: Add sandbox Fulfil credentials to config
- [ ] Route: Admin email template settings
- [ ] Controller: EmailTemplateController

---

## Dependencies

- Session 1: Data Foundation (email_records table)

## Blocks

- Session 5: Automation & Polling (uses email service)

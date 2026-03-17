<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'key' => EmailTemplate::TYPE_SKU_MAPPING_ERROR,
                'name' => 'SKU Mapping Error',
                'subject' => 'SKU Mapping Required - {{customer_name}}',
                'body' => <<<'BODY'
<p>An invoice for <strong>{{customer_name}}</strong> requires customer SKU mapping, but the following SKUs are not mapped:</p>

<p>{{missing_skus}}</p>

<p>Please add the missing SKU mappings before the invoice can be processed.</p>
BODY,
            ],
            [
                'key' => EmailTemplate::TYPE_INITIAL_INVOICE,
                'name' => 'Initial Invoice',
                'subject' => 'Invoice {{invoice_number}} from Universal Yums',
                'body' => <<<'BODY'
<p>Dear {{customer_name}},</p>

<p>Please find attached invoice {{invoice_number}} dated {{invoice_date}}.</p>

<p>
<strong>Invoice Total:</strong> {{total_amount}}<br>
<strong>Due Date:</strong> {{due_date}}
</p>

<p>Thank you for your business.</p>

<p>Best regards,<br>
Universal Yums Accounts Receivable</p>
BODY,
            ],
            [
                'key' => EmailTemplate::TYPE_INITIAL_INVOICE_AP_PORTAL,
                'name' => 'Initial Invoice - AP Portal',
                'subject' => 'ACTION REQUIRED: Upload Invoice {{invoice_number}} to AP Portal',
                'body' => <<<'BODY'
<p>Invoice {{invoice_number}} for <strong>{{customer_name}}</strong> is ready for upload.</p>

<p><strong>Customer AP Portal:</strong> <a href="{{ap_portal_url}}">{{ap_portal_url}}</a></p>

<p>
<strong>Invoice Total:</strong> {{total_amount}}<br>
<strong>Due Date:</strong> {{due_date}}
</p>

<p>Please upload the attached invoice to the customer's AP portal.</p>
BODY,
            ],
            [
                'key' => EmailTemplate::TYPE_INVOICE_MODIFIED,
                'name' => 'Invoice Modified',
                'subject' => 'Updated Invoice {{invoice_number}} from Universal Yums',
                'body' => <<<'BODY'
<p>Dear {{customer_name}},</p>

<p>Invoice {{invoice_number}} has been updated. Please find the revised invoice attached.</p>

<p>
<strong>Invoice Total:</strong> {{total_amount}}<br>
<strong>Due Date:</strong> {{due_date}}
</p>

<p>Please disregard any previous versions of this invoice.</p>

<p>Best regards,<br>
Universal Yums Accounts Receivable</p>
BODY,
            ],
            [
                'key' => EmailTemplate::TYPE_INVOICE_MODIFIED_AP_PORTAL,
                'name' => 'Invoice Modified - AP Portal',
                'subject' => 'ACTION REQUIRED: Re-upload Updated Invoice {{invoice_number}}',
                'body' => <<<'BODY'
<p>Invoice {{invoice_number}} for <strong>{{customer_name}}</strong> has been modified and needs to be re-uploaded.</p>

<p><strong>Customer AP Portal:</strong> <a href="{{ap_portal_url}}">{{ap_portal_url}}</a></p>

<p>
<strong>Invoice Total:</strong> {{total_amount}}<br>
<strong>Due Date:</strong> {{due_date}}
</p>

<p>Please upload the attached updated invoice to replace the previous version.</p>
BODY,
            ],
            [
                'key' => EmailTemplate::TYPE_DUE_REMINDER,
                'name' => 'Due Reminder',
                'subject' => 'Payment Reminder: Invoice {{invoice_number}} Due Soon',
                'body' => <<<'BODY'
<p>Dear {{customer_name}},</p>

<p>This is a friendly reminder that invoice {{invoice_number}} is due on <strong>{{due_date}}</strong>.</p>

<p><strong>Balance Due:</strong> {{balance_due}}</p>

<p>Please find the invoice attached for your reference.</p>

<p>Best regards,<br>
Universal Yums Accounts Receivable</p>
BODY,
            ],
            [
                'key' => EmailTemplate::TYPE_OVERDUE_NOTIFICATION,
                'name' => 'Overdue Notification',
                'subject' => 'Past Due: Invoice {{invoice_number}}',
                'body' => <<<'BODY'
<p>Dear {{customer_name}},</p>

<p>Invoice {{invoice_number}} was due on {{due_date}} and is now past due.</p>

<p><strong>Balance Due:</strong> {{balance_due}}</p>

<p>Please remit payment at your earliest convenience. If you have already sent payment, please disregard this notice.</p>

<p>Best regards,<br>
Universal Yums Accounts Receivable</p>
BODY,
            ],
            [
                'key' => EmailTemplate::TYPE_OVERDUE_FOLLOWUP,
                'name' => 'Overdue Follow-up',
                'subject' => 'Payment Required: Invoice {{invoice_number}} Overdue',
                'body' => <<<'BODY'
<p>Dear {{customer_name}},</p>

<p>Our records indicate that invoice {{invoice_number}} remains unpaid. The invoice was due on {{due_date}}.</p>

<p><strong>Balance Due:</strong> {{balance_due}}</p>

<p>Please contact us if you have any questions or need to discuss payment arrangements.</p>

<p>Best regards,<br>
Universal Yums Accounts Receivable</p>
BODY,
            ],
        ];

        foreach ($templates as $template) {
            EmailTemplate::updateOrCreate(
                ['key' => $template['key']],
                $template
            );
        }
    }
}

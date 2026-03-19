<?php

namespace App\Http\Controllers;

use App\Models\EmailRecord;
use App\Services\FulfilService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminEmailLogController extends Controller
{
    protected FulfilService $fulfil;

    public function __construct(FulfilService $fulfil)
    {
        $this->fulfil = $fulfil;
    }

    /**
     * Display the email activity log.
     */
    public function index(Request $request): Response
    {
        $perPage = 25;

        // Get email records with invoice relationship
        $query = EmailRecord::with('invoice')
            ->orderBy('sent_at', 'desc');

        // Filter by email type if provided
        if ($request->filled('type')) {
            $query->where('email_type', $request->input('type'));
        }

        // Paginate results
        $emailRecords = $query->paginate($perPage)->withQueryString();

        // Get customer names from Fulfil (cached)
        $customers = collect($this->fulfil->getActiveCustomers())
            ->keyBy('id')
            ->map(fn ($c) => $c['name'])
            ->toArray();

        // Transform records for frontend
        $records = $emailRecords->through(function ($record) use ($customers) {
            return [
                'id' => $record->id,
                'sent_at' => $record->sent_at->toIso8601String(),
                'email_type' => $record->email_type,
                'email_type_label' => $this->getEmailTypeLabel($record->email_type),
                'fulfil_party_id' => $record->fulfil_party_id,
                'customer_name' => $customers[$record->fulfil_party_id] ?? 'Unknown Customer',
                'invoice_id' => $record->invoice?->id,
                'invoice_fulfil_id' => $record->invoice?->fulfil_id,
                'invoice_number' => $record->invoice?->number,
                'has_pdf' => ! empty($record->pdf_path),
            ];
        });

        // Get Fulfil subdomain for building URLs
        $fulfilEnv = config('fulfil.default');
        $fulfilSubdomain = config("fulfil.environments.{$fulfilEnv}.subdomain");

        return Inertia::render('Admin/EmailLog', [
            'emailRecords' => $records,
            'filters' => [
                'type' => $request->input('type', ''),
            ],
            'emailTypes' => $this->getEmailTypeOptions(),
            'fulfilSubdomain' => $fulfilSubdomain,
        ]);
    }

    /**
     * Get human-readable label for email type.
     */
    protected function getEmailTypeLabel(string $type): string
    {
        return match ($type) {
            'initial_invoice' => 'Initial Invoice',
            'initial_invoice_ap_portal' => 'Initial Invoice (AP Portal)',
            'invoice_modified' => 'Invoice Modified',
            'invoice_modified_ap_portal' => 'Invoice Modified (AP Portal)',
            'due_reminder' => 'Due Reminder',
            'overdue_notification' => 'Overdue Notification',
            'overdue_followup' => 'Overdue Follow-up',
            'sku_mapping_error' => 'SKU Mapping Error',
            default => ucwords(str_replace('_', ' ', $type)),
        };
    }

    /**
     * Get email type options for filter dropdown.
     */
    protected function getEmailTypeOptions(): array
    {
        return [
            ['value' => '', 'label' => 'All Types'],
            ['value' => 'initial_invoice', 'label' => 'Initial Invoice'],
            ['value' => 'initial_invoice_ap_portal', 'label' => 'Initial Invoice (AP Portal)'],
            ['value' => 'invoice_modified', 'label' => 'Invoice Modified'],
            ['value' => 'invoice_modified_ap_portal', 'label' => 'Invoice Modified (AP Portal)'],
            ['value' => 'due_reminder', 'label' => 'Due Reminder'],
            ['value' => 'overdue_notification', 'label' => 'Overdue Notification'],
            ['value' => 'overdue_followup', 'label' => 'Overdue Follow-up'],
            ['value' => 'sku_mapping_error', 'label' => 'SKU Mapping Error'],
        ];
    }
}

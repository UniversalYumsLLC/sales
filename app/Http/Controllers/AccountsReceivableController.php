<?php

namespace App\Http\Controllers;

use App\Services\FulfilService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccountsReceivableController extends Controller
{
    protected FulfilService $fulfil;

    public function __construct(FulfilService $fulfil)
    {
        $this->fulfil = $fulfil;
    }

    /**
     * Display the accounts receivable list
     */
    public function index(Request $request)
    {
        $bustCache = $request->boolean('refresh');
        $search = $request->string('search')->trim();

        $customers = $this->getCustomersWithARData($bustCache);

        // Filter by search term
        if ($search->isNotEmpty()) {
            $searchLower = strtolower($search);
            $customers = array_filter($customers, function ($customer) use ($searchLower) {
                return str_contains(strtolower($customer['name']), $searchLower);
            });
        }

        // Sort by total overdue (primary), then total due (secondary)
        usort($customers, function ($a, $b) {
            if ($b['total_overdue'] !== $a['total_overdue']) {
                return $b['total_overdue'] <=> $a['total_overdue'];
            }

            return $b['total_due'] <=> $a['total_due'];
        });

        // Calculate summary totals
        $totals = [
            'total_due' => array_sum(array_column($customers, 'total_due')),
            'total_overdue' => array_sum(array_column($customers, 'total_overdue')),
            'total_severely_overdue' => array_sum(array_column($customers, 'total_severely_overdue')),
        ];

        // Get Fulfil subdomain for building invoice URLs (use actual environment from service)
        $fulfilEnv = $this->fulfil->getEnvironment();
        $fulfilSubdomain = config("fulfil.environments.{$fulfilEnv}.subdomain");

        return Inertia::render('AccountsReceivable/Index', [
            'customers' => array_values($customers),
            'totals' => $totals,
            'search' => (string) $search,
            'lastUpdated' => now()->toIso8601String(),
            'fulfilSubdomain' => $fulfilSubdomain,
            'fulfilEnvironment' => $fulfilEnv,
        ]);
    }

    /**
     * Get customers with AR data
     */
    protected function getCustomersWithARData(bool $bustCache = false): array
    {
        $customers = $this->fulfil->getActiveCustomers($bustCache);
        $invoices = $this->fulfil->getInvoices(['state' => ['validated', 'posted']], $bustCache);

        // Group invoices by party_id
        $invoicesByParty = collect($invoices)->groupBy('party_id');

        $today = Carbon::today();

        return array_filter(array_map(function ($customer) use ($invoicesByParty, $today) {
            $partyInvoices = $invoicesByParty->get($customer['id'], collect());

            // Only include customers with open invoices
            $openInvoices = $partyInvoices->where('balance', '>', 0);
            if ($openInvoices->isEmpty()) {
                return null;
            }

            // Calculate totals
            $totalDue = $openInvoices->sum('balance');
            $totalOverdue = 0;
            $totalSeverelyOverdue = 0;

            $invoiceData = $openInvoices->map(function ($invoice) use ($today, &$totalOverdue, &$totalSeverelyOverdue) {
                $daysOverdue = 0;

                if ($invoice['due_date']) {
                    $dueDate = Carbon::parse($invoice['due_date'])->startOfDay();

                    // Calculate difference: positive = overdue, negative = not yet due
                    // $dueDate->diffInDays($today, false) gives:
                    //   - positive when today is AFTER dueDate (invoice is overdue)
                    //   - negative when today is BEFORE dueDate (invoice not yet due)
                    $daysOverdue = (int) $dueDate->diffInDays($today, false);

                    if ($daysOverdue > 0) {
                        $totalOverdue += $invoice['balance'];

                        if ($daysOverdue > 30) {
                            $totalSeverelyOverdue += $invoice['balance'];
                        }
                    }
                }

                return [
                    'id' => $invoice['id'],
                    'number' => $invoice['number'],
                    'total_amount' => $invoice['total_amount'],
                    'balance' => $invoice['balance'],
                    'due_date' => $invoice['due_date'],
                    'days_overdue' => $daysOverdue,
                ];
            })->sortByDesc('days_overdue')->values()->toArray();

            // Get AP contacts with type detection
            $apContacts = array_map(function ($contact) {
                $value = $contact['value'] ?? '';
                $isPortal = str_starts_with($value, 'http://') || str_starts_with($value, 'https://');

                return [
                    'name' => $contact['name'] ?? '',
                    'value' => $value,
                    'type' => $isPortal ? 'portal' : 'inbox',
                ];
            }, $customer['accounts_payable'] ?? []);

            return [
                'id' => $customer['id'],
                'name' => $customer['name'],
                'ap_contacts' => $apContacts,
                'invoices' => $invoiceData,
                'total_due' => $totalDue,
                'total_overdue' => $totalOverdue,
                'total_severely_overdue' => $totalSeverelyOverdue,
            ];
        }, $customers), fn ($c) => $c !== null);
    }
}

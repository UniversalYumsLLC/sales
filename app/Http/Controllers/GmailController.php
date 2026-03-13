<?php

namespace App\Http\Controllers;

use App\Jobs\SyncGmailForAllUsers;
use App\Jobs\SyncGmailForUser;
use App\Models\FulfilCustomerMetadata;
use App\Models\GmailSyncHistory;
use App\Models\User;
use App\Models\UserGmailToken;
use App\Services\FulfilService;
use App\Services\GmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class GmailController extends Controller
{
    protected GmailService $gmail;

    public function __construct(GmailService $gmail)
    {
        $this->gmail = $gmail;
    }

    /**
     * Display the Gmail integration settings page.
     * Admins see all salespeople's sync history; salespeople see only their own.
     */
    public function index(): Response
    {
        $user = Auth::user();

        // Check if user can access Gmail integration
        if (!$user->canAccessGmailIntegration()) {
            abort(403, 'Gmail integration is only available for Salesperson and Admin accounts.');
        }

        // Admin view - show all salespeople's data
        if ($user->isAdmin()) {
            return $this->adminIndex();
        }

        // Salesperson view - show their own data
        return $this->salespersonIndex($user);
    }

    /**
     * Admin view: Show all salespeople's Gmail sync data.
     */
    protected function adminIndex(): Response
    {
        // Get all salespeople with their Gmail connection status
        $salespersons = User::where('role', User::ROLE_SALESPERSON)
            ->with('gmailToken')
            ->orderBy('name')
            ->get()
            ->map(fn($sp) => [
                'id' => $sp->id,
                'name' => $sp->name,
                'email' => $sp->email,
                'is_connected' => $sp->gmailToken !== null,
                'gmail_email' => $sp->gmailToken?->gmail_email,
                'connected_at' => $sp->gmailToken?->created_at->toIso8601String(),
            ]);

        // Get all sync history across all salespeople (recent 50)
        $syncHistory = GmailSyncHistory::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(fn($sync) => $this->formatSyncHistory($sync, includeUser: true));

        return Inertia::render('Gmail/Index', [
            'isAdmin' => true,
            'salespersons' => $salespersons,
            'syncHistory' => $syncHistory,
            // These are not applicable for admin view
            'isConnected' => false,
            'gmailEmail' => null,
            'connectedAt' => null,
            'lastSync' => null,
        ]);
    }

    /**
     * Salesperson view: Show their own Gmail sync data.
     */
    protected function salespersonIndex(User $user): Response
    {
        $gmailToken = $user->gmailToken;

        // Get sync history for this user
        $syncHistory = GmailSyncHistory::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($sync) => $this->formatSyncHistory($sync));

        return Inertia::render('Gmail/Index', [
            'isAdmin' => false,
            'isConnected' => $gmailToken !== null,
            'gmailEmail' => $gmailToken?->gmail_email,
            'connectedAt' => $gmailToken?->created_at->toIso8601String(),
            'lastSync' => $syncHistory->first(),
            'syncHistory' => $syncHistory,
            'salespersons' => [], // Not used for salesperson view
        ]);
    }

    /**
     * Format a sync history record for the frontend.
     */
    protected function formatSyncHistory(GmailSyncHistory $sync, bool $includeUser = false): array
    {
        $data = [
            'id' => $sync->id,
            'sync_type' => $sync->sync_type,
            'entity_type' => $sync->entity_type,
            'entity_id' => $sync->entity_id,
            'domains' => $sync->domains,
            'sync_started_at' => $sync->sync_started_at->toIso8601String(),
            'sync_completed_at' => $sync->sync_completed_at?->toIso8601String(),
            'emails_from' => $sync->emails_from->toIso8601String(),
            'emails_to' => $sync->emails_to->toIso8601String(),
            'emails_fetched' => $sync->emails_fetched,
            'emails_matched' => $sync->emails_matched,
            'status' => $sync->status,
            'error_message' => $sync->error_message,
        ];

        if ($includeUser) {
            $data['user'] = [
                'id' => $sync->user->id,
                'name' => $sync->user->name,
            ];
        }

        return $data;
    }

    /**
     * Initiate the Gmail OAuth flow.
     */
    public function connect(Request $request)
    {
        $user = Auth::user();

        if (!$user->canAccessGmailIntegration()) {
            abort(403, 'Gmail integration is only available for Salesperson accounts.');
        }

        // Generate state token for CSRF protection
        $state = Str::random(40);
        session(['gmail_oauth_state' => $state]);

        $authUrl = $this->gmail->getAuthorizationUrl($state);

        return redirect($authUrl);
    }

    /**
     * Handle the OAuth callback from Google.
     */
    public function callback(Request $request)
    {
        $user = Auth::user();

        if (!$user->canAccessGmailIntegration()) {
            abort(403, 'Gmail integration is only available for Salesperson accounts.');
        }

        // Verify state
        $state = session('gmail_oauth_state');
        if (!$state || $state !== $request->input('state')) {
            return redirect()->route('gmail.index')
                ->with('error', 'Invalid OAuth state. Please try again.');
        }

        session()->forget('gmail_oauth_state');

        // Check for errors
        if ($request->has('error')) {
            return redirect()->route('gmail.index')
                ->with('error', 'Authorization was denied: ' . $request->input('error_description', $request->input('error')));
        }

        // Exchange code for tokens
        $code = $request->input('code');
        if (!$code) {
            return redirect()->route('gmail.index')
                ->with('error', 'No authorization code received.');
        }

        try {
            $tokens = $this->gmail->exchangeCodeForTokens($code);

            // Get the Gmail email address
            $gmailEmail = $this->gmail->getGmailEmailAddress($tokens['access_token']);

            // Store the tokens
            UserGmailToken::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'gmail_email' => $gmailEmail,
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'],
                    'token_expires_at' => now()->addSeconds($tokens['expires_in']),
                ]
            );

            // Dispatch initial sync job (runs in background since it goes back 365 days)
            SyncGmailForUser::dispatch($user->id);

            return redirect()->route('gmail.index')
                ->with('success', 'Gmail connected successfully! Initial sync is running in the background.');

        } catch (\Exception $e) {
            return redirect()->route('gmail.index')
                ->with('error', 'Failed to connect Gmail: ' . $e->getMessage());
        }
    }

    /**
     * Disconnect Gmail integration.
     */
    public function disconnect(Request $request)
    {
        $user = Auth::user();

        if (!$user->canAccessGmailIntegration()) {
            abort(403);
        }

        $this->gmail->disconnect($user);

        return redirect()->route('gmail.index')
            ->with('success', 'Gmail disconnected successfully.');
    }

    /**
     * Trigger a manual sync.
     */
    public function sync(Request $request)
    {
        $user = Auth::user();

        if (!$user->canAccessGmailIntegration()) {
            abort(403);
        }

        if (!$user->hasGmailConnected()) {
            return redirect()->route('gmail.index')
                ->with('error', 'Gmail is not connected.');
        }

        // Dispatch sync job to run in background
        SyncGmailForUser::dispatch($user->id);

        return redirect()->route('gmail.index')
            ->with('success', 'Sync started. Check back shortly for results.');
    }

    /**
     * Trigger a full 365-day resync for the current salesperson.
     */
    public function fullSync(Request $request)
    {
        $user = Auth::user();

        if (!$user->canAccessGmailIntegration()) {
            abort(403);
        }

        // Only salespersons can trigger their own full sync
        if (!$user->isSalesperson()) {
            abort(403, 'Only salespersons can trigger their own full sync.');
        }

        if (!$user->hasGmailConnected()) {
            return redirect()->route('gmail.index')
                ->with('error', 'Gmail is not connected.');
        }

        // Dispatch full sync job with force flag
        SyncGmailForUser::dispatch($user->id, forceFullSync: true);

        return redirect()->route('gmail.index')
            ->with('success', 'Full 365-day resync started. This may take several minutes.');
    }

    /**
     * Trigger a full 365-day resync for all salespersons (admin only).
     */
    public function fullSyncAll(Request $request)
    {
        $user = Auth::user();

        if (!$user->isAdmin()) {
            abort(403, 'Only admins can trigger a full sync for all users.');
        }

        // Dispatch job to sync all users
        SyncGmailForAllUsers::dispatch(forceFullSync: true);

        return redirect()->route('gmail.index')
            ->with('success', 'Full 365-day resync started for all salespersons. This may take several minutes.');
    }

    /**
     * Backfill company domains from existing customer contacts (admin only).
     *
     * This extracts email domains from buyer, logistics, and accounts payable
     * contacts and populates the company_urls field for customers that don't
     * have any domains set.
     */
    public function backfillDomains(Request $request)
    {
        $user = Auth::user();

        if (!$user->isAdmin()) {
            abort(403, 'Only admins can backfill company domains.');
        }

        try {
            $fulfilService = app(FulfilService::class);
            $customers = $fulfilService->getActiveCustomers();

            $customersUpdated = 0;
            $domainsAdded = 0;

            foreach ($customers as $customer) {
                // Get or check existing metadata
                $metadata = FulfilCustomerMetadata::find($customer['id']);

                // Skip if customer already has company_urls set
                if ($metadata && !empty($metadata->company_urls)) {
                    continue;
                }

                // Extract domains from contacts
                $domains = $this->extractDomainsFromCustomer($customer);

                if (empty($domains)) {
                    continue;
                }

                // Create or update metadata with domains
                FulfilCustomerMetadata::updateOrCreate(
                    ['fulfil_party_id' => $customer['id']],
                    ['company_urls' => $domains]
                );

                $customersUpdated++;
                $domainsAdded += count($domains);
            }

            return redirect()->route('gmail.index')
                ->with('success', "Backfill complete: Updated {$customersUpdated} customers with {$domainsAdded} domains.");

        } catch (\Exception $e) {
            return redirect()->route('gmail.index')
                ->with('error', 'Failed to backfill domains: ' . $e->getMessage());
        }
    }

    /**
     * Extract email domains from a customer's contacts.
     */
    protected function extractDomainsFromCustomer(array $customer): array
    {
        $domains = [];

        // Extract from buyers
        foreach ($customer['buyers'] ?? [] as $buyer) {
            $domain = $this->extractDomainFromEmail($buyer['email'] ?? '');
            if ($domain) {
                $domains[] = $domain;
            }
        }

        // Extract from logistics
        foreach ($customer['logistics'] ?? [] as $logistics) {
            $domain = $this->extractDomainFromEmail($logistics['email'] ?? '');
            if ($domain) {
                $domains[] = $domain;
            }
        }

        // Extract from accounts_payable (value can be email or URL)
        foreach ($customer['accounts_payable'] ?? [] as $ap) {
            $value = $ap['value'] ?? '';
            // Only process if it looks like an email
            if (str_contains($value, '@')) {
                $domain = $this->extractDomainFromEmail($value);
                if ($domain) {
                    $domains[] = $domain;
                }
            }
        }

        return array_values(array_unique($domains));
    }

    /**
     * Extract domain from an email address.
     */
    protected function extractDomainFromEmail(string $email): ?string
    {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return null;
        }

        return strtolower($parts[1]);
    }
}

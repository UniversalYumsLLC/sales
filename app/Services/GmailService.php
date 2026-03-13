<?php

namespace App\Services;

use App\Models\Email;
use App\Models\FulfilBrokerContact;
use App\Models\FulfilContactMetadata;
use App\Models\FulfilCustomerMetadata;
use App\Models\FulfilUncategorizedContact;
use App\Models\GmailSyncHistory;
use App\Models\Prospect;
use App\Models\ProspectContact;
use App\Models\User;
use App\Models\UserGmailToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GmailService
{
    protected array $config;

    public function __construct()
    {
        $this->config = config('gmail');
    }

    /**
     * Generate the OAuth authorization URL.
     */
    public function getAuthorizationUrl(string $state = ''): string
    {
        $params = [
            'client_id' => $this->config['client_id'],
            'redirect_uri' => $this->config['redirect_uri'],
            'response_type' => 'code',
            'scope' => implode(' ', $this->config['scopes']),
            'access_type' => 'offline', // Required for refresh token
            'prompt' => 'consent', // Force consent to always get refresh token
            'state' => $state,
        ];

        return $this->config['oauth_url'].'?'.http_build_query($params);
    }

    /**
     * Exchange authorization code for tokens.
     */
    public function exchangeCodeForTokens(string $code): array
    {
        $response = Http::asForm()
            ->timeout(30)
            ->post($this->config['token_url'], [
                'client_id' => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
                'redirect_uri' => $this->config['redirect_uri'],
                'grant_type' => 'authorization_code',
                'code' => $code,
            ]);

        if (! $response->successful()) {
            Log::error('Gmail token exchange failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to exchange authorization code for tokens');
        }

        return $response->json();
    }

    /**
     * Refresh an expired access token.
     */
    public function refreshAccessToken(UserGmailToken $token): void
    {
        $response = Http::asForm()
            ->timeout(30)
            ->post($this->config['token_url'], [
                'client_id' => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
                'refresh_token' => $token->refresh_token,
                'grant_type' => 'refresh_token',
            ]);

        if (! $response->successful()) {
            Log::error('Gmail token refresh failed', [
                'user_id' => $token->user_id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to refresh access token');
        }

        $data = $response->json();

        $token->update([
            'access_token' => $data['access_token'],
            'token_expires_at' => now()->addSeconds($data['expires_in']),
        ]);
    }

    /**
     * Get user's Gmail email address from the API.
     */
    public function getGmailEmailAddress(string $accessToken): string
    {
        $response = Http::withToken($accessToken)
            ->timeout(15)
            ->get($this->config['api_base'].'/users/me/profile');

        if (! $response->successful()) {
            throw new \Exception('Failed to get Gmail profile');
        }

        return $response->json()['emailAddress'];
    }

    /**
     * Ensure the access token is valid, refresh if needed.
     */
    protected function ensureValidToken(UserGmailToken $token): string
    {
        if ($token->willExpireSoon()) {
            $this->refreshAccessToken($token);
            $token->refresh();
        }

        return $token->access_token;
    }

    /**
     * Sync emails for a user.
     *
     * @param  User  $user  The user to sync emails for
     * @param  bool  $forceFullSync  If true, ignores last sync and goes back full 365 days
     */
    public function syncEmails(User $user, bool $forceFullSync = false): GmailSyncHistory
    {
        $token = $user->gmailToken;
        if (! $token) {
            throw new \Exception('User does not have Gmail connected');
        }

        $accessToken = $this->ensureValidToken($token);

        $now = now();

        // Determine time range for sync
        if ($forceFullSync) {
            // Force full 365-day sync regardless of previous sync history
            $from = $now->copy()->subDays($this->config['initial_sync_days']);
        } else {
            $lastSync = GmailSyncHistory::where('user_id', $user->id)
                ->where('status', GmailSyncHistory::STATUS_COMPLETED)
                ->orderBy('emails_to', 'desc')
                ->first();

            $overlapMinutes = $this->config['sync_overlap'];

            if ($lastSync) {
                // Start from last successful sync minus overlap
                $from = $lastSync->emails_to->subMinutes($overlapMinutes);
            } else {
                // Initial sync - go back configured number of days
                $from = $now->copy()->subDays($this->config['initial_sync_days']);
            }
        }

        // Create sync history record
        $syncHistory = GmailSyncHistory::create([
            'user_id' => $user->id,
            'sync_type' => GmailSyncHistory::TYPE_FULL,
            'sync_started_at' => $now,
            'emails_from' => $from,
            'emails_to' => $now,
            'status' => GmailSyncHistory::STATUS_RUNNING,
        ]);

        try {
            // Get all company domains (prospects + active customers) for filtering
            $companyDomains = $this->getAllCompanyDomains();

            // Get all broker emails for exact email matching
            $brokerEmails = $this->getAllBrokerEmails();

            if (empty($companyDomains) && empty($brokerEmails)) {
                // No companies with domains or broker emails to match
                $syncHistory->markCompleted(0, 0);

                return $syncHistory;
            }

            // Fetch emails from Gmail, filtered by company domains and broker emails
            $domains = array_keys($companyDomains);
            $messages = $this->fetchMessagesForDomainsAndEmails($accessToken, $from, $now, $domains, array_keys($brokerEmails));
            $fetched = count($messages);
            $matched = 0;

            foreach ($messages as $messageId) {
                $emailData = $this->fetchMessageDetails($accessToken, $messageId);

                if ($emailData && $this->processEmail($user, $emailData, $companyDomains, $brokerEmails)) {
                    $matched++;
                }
            }

            $syncHistory->markCompleted($fetched, $matched);

        } catch (\Exception $e) {
            Log::error('Gmail sync failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            $syncHistory->markFailed($e->getMessage());
        }

        return $syncHistory;
    }

    /**
     * Sync emails for specific domains only.
     *
     * This is used when a new prospect or customer is created to fetch historical
     * emails for just that entity's domains, without triggering a full sync.
     *
     * @param  User  $user  The user to sync emails for
     * @param  array  $domains  The domains to sync (e.g., ['example.com'])
     * @param  string  $entityType  'prospect' or 'customer'
     * @param  int  $entityId  The ID of the prospect or fulfil_party_id
     * @return array{fetched: int, matched: int}
     */
    public function syncEmailsForDomains(User $user, array $domains, string $entityType, int $entityId): array
    {
        $token = $user->gmailToken;
        if (! $token) {
            throw new \Exception('User does not have Gmail connected');
        }

        if (empty($domains)) {
            return ['fetched' => 0, 'matched' => 0];
        }

        $accessToken = $this->ensureValidToken($token);

        // Always use the full initial sync period for domain-specific syncs
        $now = now();
        $from = $now->copy()->subDays($this->config['initial_sync_days']);

        // Build company domains mapping for just this entity
        $companyDomains = [];
        foreach ($domains as $domain) {
            $companyDomains[strtolower($domain)] = [
                'type' => $entityType,
                'id' => $entityId,
            ];
        }

        // Also get broker emails if this is a prospect with brokers
        $brokerEmails = [];
        if ($entityType === 'prospect') {
            $brokerContacts = ProspectContact::where('prospect_id', $entityId)
                ->where('type', ProspectContact::TYPE_BROKER)
                ->whereNotNull('value')
                ->get();

            foreach ($brokerContacts as $contact) {
                if (filter_var($contact->value, FILTER_VALIDATE_EMAIL)) {
                    $email = strtolower($contact->value);
                    $brokerEmails[$email] = [
                        'type' => 'prospect',
                        'id' => $entityId,
                    ];
                }
            }
        } elseif ($entityType === 'customer') {
            $brokerContacts = FulfilBrokerContact::where('fulfil_party_id', $entityId)->get();
            foreach ($brokerContacts as $contact) {
                $email = strtolower($contact->email);
                $brokerEmails[$email] = [
                    'type' => 'customer',
                    'id' => $entityId,
                ];
            }
        }

        try {
            // Fetch emails from Gmail for just these domains
            $messages = $this->fetchMessagesForDomainsAndEmails($accessToken, $from, $now, $domains, array_keys($brokerEmails));
            $fetched = count($messages);
            $matched = 0;

            foreach ($messages as $messageId) {
                $emailData = $this->fetchMessageDetails($accessToken, $messageId);

                if ($emailData && $this->processEmail($user, $emailData, $companyDomains, $brokerEmails)) {
                    $matched++;
                }
            }

            return ['fetched' => $fetched, 'matched' => $matched];

        } catch (\Exception $e) {
            Log::error('Gmail domain sync failed', [
                'user_id' => $user->id,
                'domains' => $domains,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Fetch message IDs from Gmail filtered by prospect domains and broker emails.
     *
     * Batches domains/emails to avoid query length limits and deduplicates results.
     */
    protected function fetchMessagesForDomainsAndEmails(string $accessToken, Carbon $from, Carbon $to, array $domains, array $brokerEmails = []): array
    {
        if (empty($domains) && empty($brokerEmails)) {
            return [];
        }

        $allMessages = [];

        $dateQuery = sprintf(
            'after:%s before:%s',
            $from->format('Y/m/d'),
            $to->copy()->addDay()->format('Y/m/d')
        );

        // Batch domains to avoid query length limits
        // Each domain adds ~40 chars (from:@domain.com OR to:@domain.com OR)
        // Safe batch size: ~30 domains per query
        if (! empty($domains)) {
            $domainBatches = array_chunk($domains, 30);

            foreach ($domainBatches as $batchDomains) {
                $domainQueries = [];
                foreach ($batchDomains as $domain) {
                    // Match emails from OR to this domain
                    $domainQueries[] = "from:@{$domain}";
                    $domainQueries[] = "to:@{$domain}";
                }

                // Build query: date range AND (domain1 OR domain2 OR ...)
                // Using {} syntax for OR grouping in Gmail
                $domainFilter = '{'.implode(' ', $domainQueries).'}';
                $query = "{$dateQuery} {$domainFilter}";

                Log::debug('Gmail query (domains)', ['query' => $query, 'domains_in_batch' => count($batchDomains)]);

                $batchMessages = $this->fetchMessagesWithQuery($accessToken, $query);
                $allMessages = array_merge($allMessages, $batchMessages);
            }
        }

        // Fetch broker emails by exact email address match
        if (! empty($brokerEmails)) {
            $emailBatches = array_chunk($brokerEmails, 20);

            foreach ($emailBatches as $batchEmails) {
                $emailQueries = [];
                foreach ($batchEmails as $email) {
                    // Match emails from OR to this exact email address
                    $emailQueries[] = "from:{$email}";
                    $emailQueries[] = "to:{$email}";
                }

                $emailFilter = '{'.implode(' ', $emailQueries).'}';
                $query = "{$dateQuery} {$emailFilter}";

                Log::debug('Gmail query (broker emails)', ['query' => $query, 'emails_in_batch' => count($batchEmails)]);

                $batchMessages = $this->fetchMessagesWithQuery($accessToken, $query);
                $allMessages = array_merge($allMessages, $batchMessages);
            }
        }

        // Deduplicate message IDs (in case of overlapping matches)
        return array_unique($allMessages);
    }

    /**
     * Fetch message IDs from Gmail with a specific query.
     */
    protected function fetchMessagesWithQuery(string $accessToken, string $query): array
    {
        $messages = [];
        $pageToken = null;

        do {
            $params = [
                'q' => $query,
                'maxResults' => $this->config['max_results_per_sync'],
            ];

            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $response = Http::withToken($accessToken)
                ->timeout(30)
                ->get($this->config['api_base'].'/users/me/messages', $params);

            if (! $response->successful()) {
                Log::warning('Gmail messages fetch failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'query_length' => strlen($query),
                ]);
                break;
            }

            $data = $response->json();

            foreach ($data['messages'] ?? [] as $message) {
                $messages[] = $message['id'];
            }

            $pageToken = $data['nextPageToken'] ?? null;

        } while ($pageToken && count($messages) < $this->config['max_messages_per_query']);

        return $messages;
    }

    /**
     * Fetch full message details from Gmail.
     */
    protected function fetchMessageDetails(string $accessToken, string $messageId): ?array
    {
        // Check if we already have this message
        if (Email::where('gmail_message_id', $messageId)->exists()) {
            return null;
        }

        $response = Http::withToken($accessToken)
            ->timeout(15)
            ->get($this->config['api_base'].'/users/me/messages/'.$messageId, [
                'format' => 'full',
            ]);

        if (! $response->successful()) {
            Log::warning('Gmail message fetch failed', [
                'message_id' => $messageId,
                'status' => $response->status(),
            ]);

            return null;
        }

        return $response->json();
    }

    /**
     * Process an email and store if it matches a company domain (prospect or customer) or broker email.
     */
    protected function processEmail(User $user, array $emailData, array $companyDomains, array $brokerEmails = []): bool
    {
        $headers = $this->parseHeaders($emailData['payload']['headers'] ?? []);

        $fromEmail = $this->extractEmail($headers['from'] ?? '');
        $toEmails = $this->extractEmails($headers['to'] ?? '');
        $ccEmails = $this->extractEmails($headers['cc'] ?? '');

        // Determine all email addresses involved
        $allEmails = array_merge([$fromEmail], $toEmails, $ccEmails);
        $allEmails = array_filter($allEmails);

        // Determine direction
        $userEmail = $user->gmailToken->gmail_email;
        $direction = (strtolower($fromEmail) === strtolower($userEmail))
            ? Email::DIRECTION_OUTBOUND
            : Email::DIRECTION_INBOUND;

        // Parse email body
        $body = $this->parseBody($emailData['payload']);

        // Parse email date
        $emailDate = Carbon::createFromTimestampMs($emailData['internalDate']);

        // Check for attachments
        $attachments = $this->parseAttachments($emailData['payload']);

        $processed = false;

        // First, check for broker email matches (exact email match, takes priority)
        foreach ($allEmails as $email) {
            $emailLower = strtolower($email);
            if (isset($brokerEmails[$emailLower])) {
                $brokerInfo = $brokerEmails[$emailLower];
                $this->processBrokerEmail($user, $emailData, $brokerInfo, $emailLower, $direction, $fromEmail, $toEmails, $ccEmails, $body, $emailDate, $attachments, $headers);
                $processed = true;
                // Don't break - a broker email might be linked to multiple entities
            }
        }

        // Then check for company domain matches
        $matchedDomain = null;
        $matchedEmail = null;
        $matchInfo = null;
        foreach ($allEmails as $email) {
            $domain = strtolower(explode('@', $email)[1] ?? '');
            if (isset($companyDomains[$domain])) {
                $matchedDomain = $domain;
                $matchedEmail = $email;
                $matchInfo = $companyDomains[$domain];
                break;
            }
        }

        if ($matchedDomain && $matchInfo) {
            // Handle based on company type (prospect or customer)
            if ($matchInfo['type'] === 'prospect') {
                $this->processProspectEmail($user, $emailData, $matchInfo['id'], $matchedDomain, $matchedEmail, $direction, $fromEmail, $toEmails, $ccEmails, $body, $emailDate, $attachments, $headers);
            } else {
                $this->processCustomerEmail($user, $emailData, $matchInfo['id'], $matchedDomain, $matchedEmail, $direction, $fromEmail, $toEmails, $ccEmails, $body, $emailDate, $attachments, $headers);
            }
            $processed = true;
        }

        return $processed;
    }

    /**
     * Process an email for a broker contact.
     * Updates email tracking for the broker contact without creating email records
     * (the main email record is created by processProspectEmail or processCustomerEmail).
     */
    protected function processBrokerEmail(
        User $user,
        array $emailData,
        array $brokerInfo,
        string $brokerEmail,
        string $direction,
        string $fromEmail,
        array $toEmails,
        array $ccEmails,
        array $body,
        Carbon $emailDate,
        array $attachments,
        array $headers
    ): void {
        // Update email tracking for the broker contact
        if ($brokerInfo['type'] === 'prospect') {
            // Find the prospect broker contact
            $contact = ProspectContact::where('prospect_id', $brokerInfo['id'])
                ->where('type', ProspectContact::TYPE_BROKER)
                ->whereRaw('LOWER(value) = ?', [$brokerEmail])
                ->first();

            if ($contact) {
                if ($direction === Email::DIRECTION_OUTBOUND) {
                    $contact->recordEmailSent($emailDate);
                } else {
                    $contact->recordEmailReceived($emailDate);
                }
            }
        } else {
            // Find or create the customer broker contact
            $contact = FulfilBrokerContact::where('fulfil_party_id', $brokerInfo['id'])
                ->whereRaw('LOWER(email) = ?', [$brokerEmail])
                ->first();

            if ($contact) {
                if ($direction === Email::DIRECTION_OUTBOUND) {
                    $contact->recordEmailSent($emailDate);
                } else {
                    $contact->recordEmailReceived($emailDate);
                }
            }
        }

        Log::debug('Processed broker email', [
            'broker_email' => $brokerEmail,
            'type' => $brokerInfo['type'],
            'id' => $brokerInfo['id'],
            'direction' => $direction,
        ]);
    }

    /**
     * Process an email for a prospect.
     */
    protected function processProspectEmail(
        User $user,
        array $emailData,
        int $prospectId,
        string $matchedDomain,
        string $matchedEmail,
        string $direction,
        string $fromEmail,
        array $toEmails,
        array $ccEmails,
        array $body,
        Carbon $emailDate,
        array $attachments,
        array $headers
    ): void {
        // Find the prospect contact
        $contact = ProspectContact::where('prospect_id', $prospectId)
            ->where('value', 'like', '%@'.$matchedDomain)
            ->first();

        // Create email record
        Email::create([
            'user_id' => $user->id,
            'gmail_message_id' => $emailData['id'],
            'gmail_thread_id' => $emailData['threadId'],
            'prospect_id' => $prospectId,
            'fulfil_party_id' => null,
            'contact_id' => $contact?->id,
            'direction' => $direction,
            'from_email' => $fromEmail,
            'from_name' => $this->extractName($headers['from'] ?? ''),
            'to_emails' => $toEmails,
            'cc_emails' => $ccEmails ?: null,
            'subject' => $headers['subject'] ?? null,
            'body_text' => $body['text'] ?? null,
            'body_html' => $body['html'] ?? null,
            'email_date' => $emailDate,
            'has_attachments' => ! empty($attachments),
            'attachment_info' => $attachments ?: null,
        ]);

        // Update contact email tracking for buyers
        if ($contact && $contact->type === ProspectContact::TYPE_BUYER) {
            if ($direction === Email::DIRECTION_OUTBOUND) {
                $contact->recordEmailSent($emailDate);
            } else {
                $contact->recordEmailReceived($emailDate);
            }
        }

        // Discover new contacts from this email
        $this->discoverProspectContacts($prospectId, $matchedDomain, $fromEmail, $toEmails, $ccEmails, $direction, $emailDate, $headers);
    }

    /**
     * Discover new uncategorized contacts from email addresses for a prospect.
     */
    protected function discoverProspectContacts(
        int $prospectId,
        string $matchedDomain,
        string $fromEmail,
        array $toEmails,
        array $ccEmails,
        string $direction,
        Carbon $emailDate,
        array $headers
    ): void {
        // Collect all emails that match the prospect's domain
        $allEmails = array_merge([$fromEmail], $toEmails, $ccEmails);
        $allEmails = array_filter($allEmails);
        $allEmails = array_unique(array_map('strtolower', $allEmails));

        foreach ($allEmails as $email) {
            $emailDomain = strtolower(explode('@', $email)[1] ?? '');
            if ($emailDomain !== $matchedDomain) {
                continue; // Not from the company domain
            }

            // Check if this email already exists as a contact
            $existingContact = ProspectContact::where('prospect_id', $prospectId)
                ->whereRaw('LOWER(value) = ?', [$email])
                ->first();

            if (! $existingContact) {
                // Create uncategorized contact
                $newContact = ProspectContact::create([
                    'prospect_id' => $prospectId,
                    'type' => ProspectContact::TYPE_UNCATEGORIZED,
                    'name' => $this->extractNameForEmail($email, $headers) ?? '',
                    'value' => $email,
                ]);

                // Update email tracking for the new contact
                if ($direction === Email::DIRECTION_OUTBOUND) {
                    $newContact->recordEmailSent($emailDate);
                } else {
                    $newContact->recordEmailReceived($emailDate);
                }

                Log::info('Discovered new prospect contact', [
                    'prospect_id' => $prospectId,
                    'email' => $email,
                ]);
            }
        }
    }

    /**
     * Process an email for an active customer.
     */
    protected function processCustomerEmail(
        User $user,
        array $emailData,
        int $fulfilPartyId,
        string $matchedDomain,
        string $matchedEmail,
        string $direction,
        string $fromEmail,
        array $toEmails,
        array $ccEmails,
        array $body,
        Carbon $emailDate,
        array $attachments,
        array $headers
    ): void {
        // Create email record
        Email::create([
            'user_id' => $user->id,
            'gmail_message_id' => $emailData['id'],
            'gmail_thread_id' => $emailData['threadId'],
            'prospect_id' => null,
            'fulfil_party_id' => $fulfilPartyId,
            'contact_id' => null, // Customer contacts aren't stored locally
            'direction' => $direction,
            'from_email' => $fromEmail,
            'from_name' => $this->extractName($headers['from'] ?? ''),
            'to_emails' => $toEmails,
            'cc_emails' => $ccEmails ?: null,
            'subject' => $headers['subject'] ?? null,
            'body_text' => $body['text'] ?? null,
            'body_html' => $body['html'] ?? null,
            'email_date' => $emailDate,
            'has_attachments' => ! empty($attachments),
            'attachment_info' => $attachments ?: null,
        ]);

        // Update contact metadata email tracking
        // Use the matched email address to find/create contact metadata
        $contactMetadata = FulfilContactMetadata::findOrCreateForContact($fulfilPartyId, $matchedEmail);
        if ($direction === Email::DIRECTION_OUTBOUND) {
            $contactMetadata->recordEmailSent($emailDate);
        } else {
            $contactMetadata->recordEmailReceived($emailDate);
        }

        // Discover new contacts from this email
        $this->discoverCustomerContacts($fulfilPartyId, $matchedDomain, $fromEmail, $toEmails, $ccEmails, $direction, $emailDate, $headers);
    }

    /**
     * Discover new uncategorized contacts from email addresses for a customer.
     */
    protected function discoverCustomerContacts(
        int $fulfilPartyId,
        string $matchedDomain,
        string $fromEmail,
        array $toEmails,
        array $ccEmails,
        string $direction,
        Carbon $emailDate,
        array $headers
    ): void {
        // Collect all emails that match the customer's domain
        $allEmails = array_merge([$fromEmail], $toEmails, $ccEmails);
        $allEmails = array_filter($allEmails);
        $allEmails = array_unique(array_map('strtolower', $allEmails));

        // Get existing contact emails from FulfilContactMetadata (tracks known contacts from Fulfil)
        $existingContactEmails = FulfilContactMetadata::where('fulfil_party_id', $fulfilPartyId)
            ->pluck('email')
            ->map(fn ($e) => strtolower($e))
            ->toArray();

        // Get existing uncategorized contact emails
        $existingUncategorizedEmails = FulfilUncategorizedContact::where('fulfil_party_id', $fulfilPartyId)
            ->pluck('email')
            ->map(fn ($e) => strtolower($e))
            ->toArray();

        $knownEmails = array_merge($existingContactEmails, $existingUncategorizedEmails);

        foreach ($allEmails as $email) {
            $emailDomain = strtolower(explode('@', $email)[1] ?? '');
            if ($emailDomain !== $matchedDomain) {
                continue; // Not from the company domain
            }

            if (! in_array($email, $knownEmails)) {
                // Create uncategorized contact
                $newContact = FulfilUncategorizedContact::create([
                    'fulfil_party_id' => $fulfilPartyId,
                    'name' => $this->extractNameForEmail($email, $headers) ?? '',
                    'email' => $email,
                ]);

                // Update email tracking for the new contact
                if ($direction === Email::DIRECTION_OUTBOUND) {
                    $newContact->recordEmailSent($emailDate);
                } else {
                    $newContact->recordEmailReceived($emailDate);
                }

                Log::info('Discovered new customer contact', [
                    'fulfil_party_id' => $fulfilPartyId,
                    'email' => $email,
                ]);
            }
        }
    }

    /**
     * Get all company domains (prospects + customers) mapped to company info.
     *
     * Returns: ['domain.com' => ['type' => 'prospect'|'customer', 'id' => int], ...]
     */
    protected function getAllCompanyDomains(): array
    {
        $domains = [];

        // Get prospect domains
        $prospects = Prospect::whereNotNull('company_urls')->get();
        foreach ($prospects as $prospect) {
            foreach ($prospect->getEmailDomains() as $domain) {
                $domains[$domain] = [
                    'type' => 'prospect',
                    'id' => $prospect->id,
                ];
            }
        }

        // Get customer domains from metadata
        $customerMetadata = FulfilCustomerMetadata::whereNotNull('company_urls')->get();
        foreach ($customerMetadata as $metadata) {
            foreach ($metadata->getEmailDomains() as $domain) {
                // Don't overwrite prospect domains (prospect takes priority if both have same domain)
                if (! isset($domains[$domain])) {
                    $domains[$domain] = [
                        'type' => 'customer',
                        'id' => $metadata->fulfil_party_id,
                    ];
                }
            }
        }

        return $domains;
    }

    /**
     * Get all broker email addresses mapped to their entity info.
     *
     * Returns: ['broker@email.com' => ['type' => 'prospect'|'customer', 'id' => int], ...]
     *
     * Note: We match broker emails exactly (not by domain) to avoid pulling in
     * all emails from a broker company when they represent multiple retailers.
     */
    protected function getAllBrokerEmails(): array
    {
        $emails = [];

        // Get prospect broker contacts
        $prospectBrokerContacts = ProspectContact::where('type', ProspectContact::TYPE_BROKER)
            ->whereNotNull('value')
            ->with('prospect')
            ->get();

        foreach ($prospectBrokerContacts as $contact) {
            if ($contact->prospect && filter_var($contact->value, FILTER_VALIDATE_EMAIL)) {
                $email = strtolower($contact->value);
                $emails[$email] = [
                    'type' => 'prospect',
                    'id' => $contact->prospect_id,
                ];
            }
        }

        // Get customer broker contacts
        $customerBrokerContacts = FulfilBrokerContact::all();
        foreach ($customerBrokerContacts as $contact) {
            $email = strtolower($contact->email);
            // Don't overwrite prospect broker emails (prospect takes priority)
            if (! isset($emails[$email])) {
                $emails[$email] = [
                    'type' => 'customer',
                    'id' => $contact->fulfil_party_id,
                ];
            }
        }

        return $emails;
    }

    /**
     * Parse email headers into an associative array.
     */
    protected function parseHeaders(array $headers): array
    {
        $result = [];
        foreach ($headers as $header) {
            $name = strtolower($header['name']);
            $result[$name] = $header['value'];
        }

        return $result;
    }

    /**
     * Extract email address from a header value like "Name <email@domain.com>".
     */
    protected function extractEmail(string $value): string
    {
        if (preg_match('/<([^>]+)>/', $value, $matches)) {
            return strtolower(trim($matches[1]));
        }

        return strtolower(trim($value));
    }

    /**
     * Extract name from a header value like "Name <email@domain.com>".
     */
    protected function extractName(string $value): ?string
    {
        if (preg_match('/^"?([^"<]+)"?\s*</', $value, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Find the name associated with an email address from email headers.
     * Searches through from, to, and cc headers to find the matching email and extract the name.
     */
    protected function extractNameForEmail(string $email, array $headers): ?string
    {
        $emailLower = strtolower($email);

        // Check each header field that might contain the email
        foreach (['from', 'to', 'cc'] as $field) {
            if (empty($headers[$field])) {
                continue;
            }

            // Split by comma for multiple recipients
            $parts = preg_split('/,\s*/', $headers[$field]);
            foreach ($parts as $part) {
                $partEmail = $this->extractEmail($part);
                if (strtolower($partEmail) === $emailLower) {
                    $name = $this->extractName($part);
                    if ($name) {
                        return $name;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Extract multiple email addresses from a header.
     */
    protected function extractEmails(string $value): array
    {
        if (empty($value)) {
            return [];
        }

        $emails = [];
        $parts = preg_split('/,\s*/', $value);

        foreach ($parts as $part) {
            $email = $this->extractEmail($part);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        }

        return $emails;
    }

    /**
     * Parse email body from the payload.
     */
    protected function parseBody(array $payload): array
    {
        $body = ['text' => null, 'html' => null];

        // Direct body
        if (isset($payload['body']['data'])) {
            $content = $this->decodeBody($payload['body']['data']);
            $mimeType = $payload['mimeType'] ?? 'text/plain';

            if (str_contains($mimeType, 'text/plain')) {
                $body['text'] = $content;
            } elseif (str_contains($mimeType, 'text/html')) {
                $body['html'] = $content;
            }
        }

        // Multipart body
        if (isset($payload['parts'])) {
            foreach ($payload['parts'] as $part) {
                $partBody = $this->parseBody($part);
                if ($partBody['text'] && ! $body['text']) {
                    $body['text'] = $partBody['text'];
                }
                if ($partBody['html'] && ! $body['html']) {
                    $body['html'] = $partBody['html'];
                }
            }
        }

        return $body;
    }

    /**
     * Decode base64url encoded body.
     */
    protected function decodeBody(string $data): string
    {
        $data = str_replace(['-', '_'], ['+', '/'], $data);

        return base64_decode($data);
    }

    /**
     * Parse attachment information from the payload.
     */
    protected function parseAttachments(array $payload, array $attachments = []): array
    {
        if (isset($payload['filename']) && ! empty($payload['filename'])) {
            $attachments[] = [
                'filename' => $payload['filename'],
                'mimeType' => $payload['mimeType'] ?? 'application/octet-stream',
                'size' => $payload['body']['size'] ?? 0,
            ];
        }

        if (isset($payload['parts'])) {
            foreach ($payload['parts'] as $part) {
                $attachments = $this->parseAttachments($part, $attachments);
            }
        }

        return $attachments;
    }

    /**
     * Disconnect Gmail integration for a user.
     */
    public function disconnect(User $user): void
    {
        $token = $user->gmailToken;
        if ($token) {
            // Optionally revoke the token with Google
            try {
                Http::timeout(10)->post('https://oauth2.googleapis.com/revoke', [
                    'token' => $token->access_token,
                ]);
            } catch (\Exception $e) {
                // Ignore revocation errors
            }

            $token->delete();
        }
    }
}

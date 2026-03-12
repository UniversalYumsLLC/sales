<?php

namespace App\Http\Controllers;

use App\Models\UserGmailToken;
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
     */
    public function index(): Response
    {
        $user = Auth::user();

        // Check if user can access Gmail integration
        if (!$user->canAccessGmailIntegration()) {
            abort(403, 'Gmail integration is only available for Salesperson accounts.');
        }

        $gmailToken = $user->gmailToken;
        $syncHistory = $user->gmailToken
            ? $user->gmailToken->user->load(['gmailToken'])->gmailToken->user
                ->hasMany(\App\Models\GmailSyncHistory::class)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
            : collect();

        // Get sync history directly
        $syncHistory = \App\Models\GmailSyncHistory::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($sync) => [
                'id' => $sync->id,
                'sync_started_at' => $sync->sync_started_at->toIso8601String(),
                'sync_completed_at' => $sync->sync_completed_at?->toIso8601String(),
                'emails_from' => $sync->emails_from->toIso8601String(),
                'emails_to' => $sync->emails_to->toIso8601String(),
                'emails_fetched' => $sync->emails_fetched,
                'emails_matched' => $sync->emails_matched,
                'status' => $sync->status,
                'error_message' => $sync->error_message,
            ]);

        return Inertia::render('Gmail/Index', [
            'isConnected' => $gmailToken !== null,
            'gmailEmail' => $gmailToken?->gmail_email,
            'connectedAt' => $gmailToken?->created_at->toIso8601String(),
            'lastSync' => $syncHistory->first(),
            'syncHistory' => $syncHistory,
        ]);
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

            return redirect()->route('gmail.index')
                ->with('success', 'Gmail connected successfully! Initial sync will begin shortly.');

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

        try {
            $syncHistory = $this->gmail->syncEmails($user);

            if ($syncHistory->status === 'completed') {
                return redirect()->route('gmail.index')
                    ->with('success', "Sync completed. Fetched {$syncHistory->emails_fetched} emails, matched {$syncHistory->emails_matched} to prospects.");
            } else {
                return redirect()->route('gmail.index')
                    ->with('error', 'Sync failed: ' . $syncHistory->error_message);
            }

        } catch (\Exception $e) {
            return redirect()->route('gmail.index')
                ->with('error', 'Sync failed: ' . $e->getMessage());
        }
    }
}

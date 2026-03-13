<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserInvite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;

/**
 * SocialAuthController
 *
 * Handles Google OAuth authentication flow.
 *
 * OAuth Flow:
 * 1. User clicks "Sign in with Google" -> redirect() sends to Google
 * 2. User approves at Google -> Google redirects to callback()
 * 3. callback() validates domain, creates/updates user, logs them in
 * 4. User redirected to home dashboard
 */
class SocialAuthController extends Controller
{
    /**
     * Memoized allowed domains from config.
     */
    protected ?array $allowedDomains = null;

    /**
     * Memoized allowed emails from config.
     */
    protected ?array $allowedEmails = null;

    /**
     * Redirect to OAuth provider with minimum required scopes.
     */
    public function redirect(string $provider): RedirectResponse
    {
        if ($provider !== 'google') {
            return redirect()->route('login')->with('error', 'Invalid provider');
        }

        /** @var GoogleProvider $driver */
        $driver = Socialite::driver('google');

        return $driver
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    /**
     * Handle OAuth callback from Google.
     *
     * Validates user email against allowed domains and emails whitelist.
     * Creates or updates user and logs them in with remember me enabled.
     */
    public function callback(string $provider): RedirectResponse
    {
        if ($provider !== 'google') {
            return redirect()->route('login')->with('error', 'Invalid provider');
        }

        try {
            $socialUser = Socialite::driver('google')->user();

            if (! $socialUser->getEmail() || ! $socialUser->getName()) {
                Log::warning('OAuth missing required user data', [
                    'provider' => $provider,
                    'has_email' => ! empty($socialUser->getEmail()),
                    'has_name' => ! empty($socialUser->getName()),
                    'ip' => request()->ip(),
                ]);

                return redirect()->route('login')
                    ->with('error', 'Authentication failed. Please try again.');
            }

            if (! $this->isEmailAllowed($socialUser->getEmail())) {
                Log::warning('OAuth email rejected', [
                    'provider' => $provider,
                    'email' => strtolower($socialUser->getEmail()),
                    'ip' => request()->ip(),
                ]);

                return redirect()->route('login')
                    ->with('error', 'Access denied. Please contact your administrator.');
            }

            $user = User::where('google_id', $socialUser->getId())
                ->orWhere('email', $socialUser->getEmail())
                ->first();

            if ($user) {
                $user->update([
                    'name' => $socialUser->getName(),
                    'email' => $socialUser->getEmail(),
                    'google_id' => $socialUser->getId(),
                    'avatar' => $socialUser->getAvatar(),
                ]);
            } else {
                $email = strtolower($socialUser->getEmail());

                // Check for invite to get role
                $invite = UserInvite::getByEmail($email);
                if ($invite) {
                    $role = $invite->role;
                    $invite->delete(); // Remove invite after use
                } elseif ($email === 'eli@universalyums.com') {
                    $role = User::ROLE_ADMIN;
                } else {
                    $role = User::ROLE_USER;
                }

                $user = User::create([
                    'name' => $socialUser->getName(),
                    'email' => $socialUser->getEmail(),
                    'google_id' => $socialUser->getId(),
                    'avatar' => $socialUser->getAvatar(),
                    'role' => $role,
                ]);
            }

            Auth::login($user, true);

            return redirect()->intended(route('customers.index'));

        } catch (InvalidStateException $e) {
            Log::warning('OAuth invalid state', [
                'provider' => $provider,
                'message' => $e->getMessage(),
                'ip' => request()->ip(),
            ]);

            return redirect()->route('login')
                ->with('error', 'Authentication session expired. Please try again.');

        } catch (\Exception $e) {
            Log::error('OAuth authentication failed', [
                'provider' => $provider,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'ip' => request()->ip(),
            ]);

            return redirect()->route('login')
                ->with('error', 'Authentication failed. Please try again.');
        }
    }

    /**
     * Get allowed domains from config.
     */
    protected function getAllowedDomains(): array
    {
        if ($this->allowedDomains !== null) {
            return $this->allowedDomains;
        }

        $domains = (string) config('auth.allowed_domains', '');
        $this->allowedDomains = array_filter(
            array_map('trim', explode(',', $domains))
        );

        return $this->allowedDomains;
    }

    /**
     * Get allowed emails from config.
     */
    protected function getAllowedEmails(): array
    {
        if ($this->allowedEmails !== null) {
            return $this->allowedEmails;
        }

        $emails = (string) config('auth.allowed_emails', '');
        $this->allowedEmails = array_filter(
            array_map('trim', explode(',', $emails))
        );

        return $this->allowedEmails;
    }

    /**
     * Check if an email address is allowed to authenticate.
     */
    protected function isEmailAllowed(string $email): bool
    {
        $email = strtolower(trim($email));

        // Check domain whitelist
        $allowedDomains = $this->getAllowedDomains();
        if (! empty($allowedDomains)) {
            $emailDomain = substr(strrchr($email, '@'), 1);
            foreach ($allowedDomains as $domain) {
                if (strtolower($domain) === $emailDomain) {
                    return true;
                }
            }
        }

        // Check email whitelist from config
        $allowedEmails = $this->getAllowedEmails();
        if (collect($allowedEmails)->contains(fn ($allowedEmail) => strtolower($allowedEmail) === $email)) {
            return true;
        }

        // Check database invites
        if (UserInvite::hasInvite($email)) {
            return true;
        }

        return false;
    }
}

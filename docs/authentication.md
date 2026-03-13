# Authentication System

## Google OAuth Integration (Exclusive)

**Authentication Method:** This application uses Google OAuth as the EXCLUSIVE authentication method. Password-based login is not used.

### How Authentication Works

1. Users click "Sign in with Google" on the login page
2. Redirected to Google's OAuth consent screen (scopes: `openid`, `profile`, `email`)
3. After approval, user is auto-provisioned (first time) or logged in (returning)
4. Profile data (name, email, avatar) syncs from Google on every login
5. Extended sessions ("remember me") enabled automatically for all OAuth users

### Access Control

- **Domain Validation:** Access restricted to approved email domains configured in `ALLOWED_AUTH_DOMAINS`
- **Email Whitelist:** Individual emails can be whitelisted via `ALLOWED_AUTH_EMAILS` for contractors or partners
- **User Invites:** Admins can pre-authorize users via the invite system, which also assigns a role
- **Case-Insensitive:** Domain and email matching is case-insensitive
- **Fail-Safe Default:** Empty configuration defaults to NO ACCESS (secure by default)

### Auto-Provisioning

- First-time users are automatically created upon successful Google authentication
- No manual user creation required
- User profile includes: name, email, google_id, avatar URL
- Default role: `user` (unless an invite specifies a different role)

### User Invite System

Admins can pre-authorize users and assign roles before their first login:

1. Admin visits `/admin/users` and creates an invite (email + role)
2. Invite stored in `user_invites` table
3. When the invited user logs in via Google OAuth, they receive the assigned role
4. Invite is consumed after first use

### Roles

| Role            | Constant          | Access                      |
| --------------- | ----------------- | --------------------------- |
| **Admin**       | `User::ROLE_ADMIN`       | Full access, user management |
| **Salesperson** | `User::ROLE_SALESPERSON` | Customer data, Gmail integration |
| **User**        | `User::ROLE_USER`        | Basic read access            |

### Profile Synchronization

- User profile data syncs from Google on EVERY login
- Fields synced: name, email, google_id, avatar URL
- Profile fields are read-only (users update via Google account settings)

### Session Management

- Session lifetime: Configured via `SESSION_LIFETIME` environment variable (default: 120 minutes)
- Session driver: `database`
- Extended sessions: Always enabled for OAuth users (persistent across browser closes)
- Logout: Clears local session only (does not revoke Google OAuth tokens)
- After logout: Redirects to `/login`

### Security Logging

- Failed authentication attempts logged for security monitoring
- Rejected domains/emails logged with reason for rejection
- Check `storage/logs/laravel.log` for OAuth-related events

### Google Cloud Console Setup

1. Create OAuth 2.0 Client ID at [Google Cloud Console](https://console.cloud.google.com/apis/credentials)
2. Configure authorized redirect URIs:
    - Development (localhost): `http://localhost/auth/google/callback`
    - Development (`.test` domains via Herd): use the [fwd.host workaround](https://herd.laravel.com/docs/macos/advanced-usage/social-auth#using-the-fwd-host-webservice) — set `GOOGLE_REDIRECT_URI="https://fwd.host/${APP_URL}/auth/google/callback"` in `.env` and add the same URI in Google Cloud Console
    - Production: `https://your-domain.com/auth/google/callback`
3. Enable Google+ API (required for profile/avatar access)
4. Set `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` in `.env`
5. Use separate OAuth clients for development and production

### Troubleshooting

| Problem                          | Solution                                                                               |
| -------------------------------- | -------------------------------------------------------------------------------------- |
| "Access denied" error            | Verify user's domain is in `ALLOWED_AUTH_DOMAINS` or email is in `ALLOWED_AUTH_EMAILS` or invite exists |
| "Authentication session expired" | Clear browser cookies, verify `SESSION_DRIVER=database`, ensure `APP_KEY` is set       |
| Redirect URI mismatch            | Verify `GOOGLE_REDIRECT_URI` exactly matches Google Cloud Console authorized URI       |
| Profile changes don't appear     | Have user log out and log back in to sync latest profile data                          |
| Avatar not displaying            | Enable Google+ API in Cloud Console, check browser console for errors                  |

### Key Files

- `app/Http/Controllers/Auth/SocialAuthController.php` - OAuth flow (redirect, callback, email validation)
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php` - Login page rendering, logout
- `resources/js/Pages/Auth/Login.tsx` - OAuth-only login page
- `app/Models/User.php` - User model with OAuth fields and role constants
- `app/Models/UserInvite.php` - Invite model for pre-authorizing users
- `app/Http/Middleware/AdminMiddleware.php` - Admin route protection
- `config/auth.php` - Access control configuration (domains, emails, admins)
- `routes/auth.php` - Auth route definitions

### Environment Variables

```env
GOOGLE_CLIENT_ID=                              # From Google Cloud Console
GOOGLE_CLIENT_SECRET=                          # From Google Cloud Console
GOOGLE_REDIRECT_URI=${APP_URL}/auth/google/callback
ALLOWED_AUTH_DOMAINS=universalyums.com         # Comma-separated domains
ALLOWED_AUTH_EMAILS=                           # Comma-separated individual emails
ADMIN_EMAILS=eli@universalyums.com             # Comma-separated admin emails
SESSION_LIFETIME=120
SESSION_DRIVER=database
```

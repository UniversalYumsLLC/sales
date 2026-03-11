# Production Deployment Checklist

This document outlines the steps required to deploy the Sales Dashboard application to production.

## Environment Configuration

### 1. Application Settings
- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Set `APP_URL` to production domain (e.g., `https://sales.universalyums.com`)
- [ ] Generate new `APP_KEY` for production (`php artisan key:generate`)

### 2. Database
- [ ] Configure production database connection (`DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, etc.)
- [ ] Run migrations: `php artisan migrate --force`

### 3. Google OAuth Configuration
- [ ] Add production redirect URI in [Google Cloud Console](https://console.cloud.google.com/apis/credentials):
  ```
  https://your-production-domain.com/auth/google/callback
  ```
- [ ] Set `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` in production `.env`
- [ ] Verify `GOOGLE_REDIRECT_URI` resolves correctly (uses `APP_URL`)

### 4. Access Control
- [ ] Verify `ALLOWED_AUTH_DOMAINS` is set correctly (e.g., `universalyums.com`)
- [ ] Add any external emails to `ALLOWED_AUTH_EMAILS` if needed
- [ ] Set `ADMIN_EMAILS` for users who need admin access

### 5. Fulfil API
- [ ] Set `FULFIL_DEFAULT_ENV=production`
- [ ] Configure `FULFIL_PRODUCTION_SUBDOMAIN` and `FULFIL_PRODUCTION_TOKEN`
- [ ] Verify API connectivity from production server

## Server Setup

### 6. Web Server
- [ ] Configure Nginx/Apache for Laravel
- [ ] Set document root to `/public`
- [ ] Configure SSL certificate (HTTPS required for OAuth)

### 7. PHP Configuration
- [ ] PHP 8.2+ installed
- [ ] Required extensions: `pdo`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`
- [ ] Set appropriate `memory_limit` and `max_execution_time`

### 8. File Permissions
- [ ] `storage/` directory writable by web server
- [ ] `bootstrap/cache/` directory writable by web server

## Build & Optimization

### 9. Frontend Assets
- [ ] Run `npm ci` (clean install)
- [ ] Run `npm run build`

### 10. Laravel Optimization
- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] `php artisan view:cache`

## Post-Deployment

### 11. Verification
- [ ] Test Google OAuth login flow
- [ ] Verify Fulfil API data loads correctly
- [ ] Check error logging is working
- [ ] Test with `eli@universalyums.com` account

### 12. Monitoring
- [ ] Set up error tracking (e.g., Sentry, Bugsnag)
- [ ] Configure log rotation
- [ ] Set up uptime monitoring

## Rollback Plan

If issues occur:
1. Revert to previous deployment
2. Clear caches: `php artisan cache:clear && php artisan config:clear`
3. Check Laravel logs: `storage/logs/laravel.log`

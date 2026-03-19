# Sales Application

A B2B sales enablement tool for Universal Yums, built with Laravel 12 and Inertia.js. It integrates with Fulfil ERP for customer and invoice data, and with Gmail for email tracking. The application supports the full sales cycle from prospecting through accounts receivable collection.

**Production URL:** sales.yums.com

## Features

### Active Customers

The home page. Displays wholesale customers synced from Fulfil ERP, including open purchase orders, trailing 12-month revenue, overdue invoices, contacts (buyers, AP, brokers), and SKU mappings. Customer data is cached from Fulfil and can be refreshed on demand.

### Prospects

A local sales pipeline for tracking potential customers before they exist in Fulfil. Prospects move through statuses: target, contacted, engaged, and dormant. Once qualified, a prospect can be promoted into a Fulfil customer record. Each prospect can have multiple contacts and product interests.

### Accounts Receivable Automation

Fetches posted invoices from Fulfil and drives automated email sequences for collections:
1. Initial invoice PDF delivery
2. Due reminder (7 days before due date)
3. Overdue notification (1 day after due date)
4. Weekly follow-up emails

Invoice PDFs are generated locally via DomPDF. Email templates are configurable through the admin interface.

### Gmail Integration

Salespersons connect their Gmail accounts via OAuth (read-only scope). The system syncs up to 365 days of email history and matches emails to customers and prospects by domain. Sync runs automatically every 15 minutes and can be triggered manually by admins.

### User Management

Admins can invite users, assign roles, and manage access. Authentication uses Google OAuth with domain and email-based restrictions.

## User Roles

| Role | Access |
|------|--------|
| **Admin** | Full access: user management, Gmail admin tools, AR automation settings, test mode toggle |
| **Salesperson** | Manage prospects, view customers, connect personal Gmail, view emails |
| **User** | Read-only access to customers and prospects |

## Tech Stack

### Backend

- PHP 8.2+
- Laravel 12.x
- Inertia.js 2.0 (server-side adapter for SPA rendering)
- Laravel Socialite (Google OAuth)
- Predis (Redis client)
- Ziggy (exposes Laravel routes to JavaScript)
- DomPDF (PDF generation)
- Guzzle (HTTP client for Fulfil API)

### Frontend

- React 18 with TypeScript
- Vite 7 (bundler)
- Tailwind CSS with `@tailwindcss/forms`
- Radix UI (accessible primitives: dialog, dropdown, tabs, tooltip, collapsible)
- Lucide React (icons)
- Recharts (data visualization)
- date-fns (date utilities)
- Axios (HTTP client, configured with CSRF handling)

### Database and Caching

- SQLite for local development
- MySQL 8.0 in production
- Redis for caching (Fulfil API responses, rate limiting)
- Database-backed queue for background jobs
- Database-backed sessions

### Testing and Code Quality

- Pest (PHPUnit) with Laravel plugin, in-memory SQLite for test isolation
- Laravel Pint (PHP code style)
- ESLint 9 + Prettier (TypeScript/JavaScript formatting)

## Architecture

### Directory Structure

```
app/
├── Console/Commands/         # Artisan commands (gmail:sync, fulfil:warm-cache, etc.)
├── Http/
│   ├── Controllers/          # Request handlers
│   ├── Middleware/
│   └── Requests/             # Form request validation
├── Jobs/                     # Background jobs
├── Models/                   # Eloquent models
├── Notifications/            # User notifications
├── Providers/                # Service providers
└── Services/                 # Business logic

resources/js/
├── Components/               # Reusable React components
├── Layouts/                  # Page layouts
├── Pages/                    # Inertia page components
│   ├── AccountsReceivable/
│   ├── ActiveCustomers/
│   ├── Admin/
│   ├── Auth/
│   ├── Gmail/
│   ├── Profile/
│   └── Prospects/
└── types/                    # TypeScript definitions
```

### Key Services

| Service | Responsibility |
|---------|---------------|
| `FulfilService` | All Fulfil ERP API interactions: customers, invoices, contacts, price lists, products |
| `GmailService` | Gmail OAuth token management, email fetching and syncing |
| `ArAutomationService` | Accounts receivable workflow orchestration |
| `ArEmailService` | AR email sequence logic and delivery |
| `InvoicePdfService` | Invoice PDF generation via DomPDF |
| `TestModeService` | Toggle between Fulfil sandbox and production environments |

### Background Jobs

| Job | Purpose |
|-----|---------|
| `SyncGmailForUser` | Full 365-day email sync for a single salesperson |
| `SyncGmailForDomains` | Targeted sync for specific domains (triggered on prospect/customer creation) |
| `SyncGmailForAllUsers` | Admin-triggered sync for all connected salespersons |
| `ProcessArInvoicesJob` | Process invoices through the AR automation pipeline |

### Artisan Commands

| Command | Purpose |
|---------|---------|
| `gmail:sync` | Scheduled command that runs every 15 minutes to sync Gmail |
| `fulfil:warm-cache` | Pre-populates Fulfil API cache to avoid cold-start timeouts |
| `fulfil:discover-metafields` | Discovers available Fulfil metafield configurations |

## Prerequisites

- PHP 8.2+
- Composer
- Node.js and npm
- SQLite (development) or MySQL 8.0 (production)
- Redis (optional for development; required for production caching)

## Development Setup

1. Clone the repository:

   ```bash
   git clone <repo-url> && cd uy-sales
   ```

2. Copy the environment file and configure it (see [Environment Variables](#environment-variables)):

   ```bash
   cp .env.example .env
   ```

3. Install dependencies and generate app key:

   ```bash
   composer install
   npm install
   php artisan key:generate
   ```

4. Create the SQLite database and run migrations:

   ```bash
   touch database/database.sqlite
   php artisan migrate
   ```

5. Start the full development environment (web server, queue worker, log tail, and Vite):

   ```bash
   composer run dev
   ```

   This runs concurrently:
   - `php artisan serve` -- Laravel dev server
   - `php artisan queue:listen` -- Queue worker
   - `php artisan pail` -- Log tail
   - `npm run dev` -- Vite dev server

## Available Commands

```bash
# Development
composer run dev              # Full dev environment (server + queue + logs + vite)
npm run dev                   # Vite dev server only
npm run build                 # Production frontend build

# Testing
php artisan test              # Run full test suite
php artisan test --filter=Name  # Run specific test(s)
composer run test             # Clear config cache, then run tests

# Code Quality
vendor/bin/pint               # Format all PHP files
vendor/bin/pint --dirty       # Format only changed PHP files
npm run lint                  # ESLint
npm run format                # Prettier

# Fulfil
php artisan fulfil:warm-cache           # Pre-populate Fulfil API cache
php artisan fulfil:discover-metafields  # Discover Fulfil metafield configs

# Gmail
php artisan gmail:sync        # Trigger Gmail sync for all connected users
```

## Environment Variables

Copy `.env.example` to `.env` and configure the following groups. Do not commit actual values.

### Application

| Variable | Description |
|----------|-------------|
| `APP_NAME` | Application display name |
| `APP_ENV` | Environment: `local`, `staging`, or `production` |
| `APP_KEY` | Generated via `php artisan key:generate` |
| `APP_DEBUG` | Enable debug mode (`true` for dev, `false` for production) |
| `APP_URL` | Base URL of the application |

### Database

| Variable | Description |
|----------|-------------|
| `DB_CONNECTION` | Database driver: `sqlite` (dev) or `mysql` (production) |
| `DB_HOST` | MySQL host (production only) |
| `DB_PORT` | MySQL port (production only) |
| `DB_DATABASE` | MySQL database name (production only) |
| `DB_USERNAME` | MySQL username (production only) |
| `DB_PASSWORD` | MySQL password (production only) |

### Cache and Queue

| Variable | Description |
|----------|-------------|
| `CACHE_STORE` | Cache backend: `database` (dev) or `redis` (production) |
| `QUEUE_CONNECTION` | Queue backend: `database` |
| `REDIS_HOST` | Redis host (when using Redis cache) |
| `REDIS_PORT` | Redis port |
| `REDIS_PASSWORD` | Redis password |

### Google OAuth (Authentication)

| Variable | Description |
|----------|-------------|
| `GOOGLE_CLIENT_ID` | OAuth client ID from Google Cloud Console |
| `GOOGLE_CLIENT_SECRET` | OAuth client secret |
| `GOOGLE_REDIRECT_URI` | OAuth callback URL (defaults to `{APP_URL}/auth/google/callback`) |

### Access Control

| Variable | Description |
|----------|-------------|
| `ALLOWED_AUTH_DOMAINS` | Comma-separated allowed email domains |
| `ALLOWED_AUTH_EMAILS` | Comma-separated individual email addresses (for external users) |
| `ADMIN_EMAILS` | Comma-separated admin email addresses |

### Fulfil ERP

| Variable | Description |
|----------|-------------|
| `FULFIL_DEFAULT_ENV` | Which Fulfil environment to use: `sandbox` or `production` |
| `FULFIL_SANDBOX_SUBDOMAIN` | Fulfil sandbox subdomain |
| `FULFIL_SANDBOX_TOKEN` | Fulfil sandbox API token |
| `FULFIL_PRODUCTION_SUBDOMAIN` | Fulfil production subdomain |
| `FULFIL_PRODUCTION_TOKEN` | Fulfil production API token |
| `FULFIL_CACHE_TTL` | Cache duration in seconds (default: 3600) |
| `FULFIL_MAX_RETRIES` | Max API retry attempts (default: 3) |

### Gmail Integration

| Variable | Description |
|----------|-------------|
| `GMAIL_CLIENT_ID` | Gmail OAuth client ID (can reuse `GOOGLE_CLIENT_ID`) |
| `GMAIL_CLIENT_SECRET` | Gmail OAuth client secret (can reuse `GOOGLE_CLIENT_SECRET`) |
| `GMAIL_REDIRECT_URI` | Gmail OAuth callback URL (defaults to `{APP_URL}/gmail/callback`) |
| `GMAIL_SYNC_INTERVAL` | Sync interval in minutes (default: 15) |
| `GMAIL_SYNC_OVERLAP` | Overlap window in minutes to prevent gaps (default: 2) |

### Mail (AR Automation)

| Variable | Description |
|----------|-------------|
| `MAIL_MAILER` | Mail driver: `log` (dev) or `smtp` (production) |
| `MAIL_HOST` | SMTP host (production uses `smtp-relay.gmail.com`) |
| `MAIL_PORT` | SMTP port (587 for TLS, 465 for SSL) |
| `MAIL_USERNAME` | SMTP username (if using authenticated relay) |
| `MAIL_PASSWORD` | SMTP password (if using authenticated relay) |
| `MAIL_FROM_ADDRESS` | Default sender address |
| `MAIL_FROM_NAME` | Default sender name |
| `MAIL_AR_FROM_ADDRESS` | AR automation sender address (Google Group) |

## External Integrations

### Fulfil ERP

The application communicates with Fulfil ERP via its REST API (`https://{subdomain}.fulfil.io/api/v2/`). Authentication uses Personal Access Tokens sent via the `X-API-KEY` header.

Both sandbox and production Fulfil environments are supported. Admins can toggle between them at runtime via the test mode setting. API responses are cached in Redis to minimize latency and respect rate limits.

Key Fulfil models used:

| Model | Purpose |
|-------|---------|
| `party.party` | Customers and contacts |
| `party.contact_mechanism` | Emails, phones, and custom data fields |
| `party.category` | Categories including shipping terms |
| `account.invoice` | Invoices for AR tracking |
| `account.invoice.payment_term` | Payment terms (Net 30, etc.) |
| `product.price_list` | Price lists for discount extraction |

### Gmail API

Salespersons authorize read-only Gmail access via OAuth 2.0. The application syncs emails matching customer and prospect domains, storing them locally for display alongside customer records. Sync state is tracked per-user in the `gmail_sync_history` table.

Required OAuth scopes: `gmail.readonly`, `userinfo.email`

### Google Workspace SMTP Relay

AR automation emails are sent through Google Workspace SMTP Relay (`smtp-relay.gmail.com`). This requires configuration in both the application environment and the Google Admin Console.

## Testing

Tests use Pest (built on PHPUnit) with an in-memory SQLite database for isolation.

```bash
# Run the full test suite
php artisan test

# Run with compact output
php artisan test --compact

# Run a specific test class or method
php artisan test --filter=ActiveCustomerTest
```

## Deployment

The application is hosted on **Laravel Forge** and deploys automatically when changes are pushed to the `main` branch.

The deploy process:
1. Install PHP dependencies (no dev)
2. Run `php artisan optimize` (cache config, routes, views)
3. Run database migrations
4. Install Node dependencies and build frontend assets
5. Activate the new release
6. Restart queue workers
7. Warm the Fulfil cache in the background

**Scheduled jobs:** The Forge scheduler runs `php artisan schedule:run` every minute, which handles the 15-minute Gmail sync cycle and AR invoice processing.

**Queue:** Uses the database driver with a daemon worker managed by Forge. Jobs include Gmail syncing and AR invoice processing.

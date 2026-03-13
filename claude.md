# Sales Application

## Overview
A tool suite supporting the B2B sales function, integrating with Fulfil ERP for customer data and Gmail for email tracking.

## Current Features
- **Active Customers**: View and manage customers synced from Fulfil ERP
- **Prospects**: Track potential customers before they're added to Fulfil
- **Accounts Receivable**: Monitor outstanding invoices and aging
- **Gmail Integration**: Sync emails between salespersons and customer contacts
- **User Management**: Admin tools for inviting users and managing roles

## User Roles
- **Admin**: Full access including user management and Gmail admin tools
- **Salesperson**: Can manage prospects, view customers, connect Gmail
- **User**: Basic read-only access

## Technology Stack

### Backend
- PHP 8.2+
- Laravel 12.x
- Inertia.js 2.0 (server-side SPA rendering)
- Laravel Socialite (Google OAuth)
- Predis (Redis client for caching)
- Ziggy (type-safe routes in JavaScript)

### Frontend
- React 19
- TypeScript 5.7+
- Vite 6 (bundler)
- Tailwind CSS 3.x
- Radix UI (accessible components)
- Lucide React (icons)
- Recharts (data visualization)
- date-fns (date utilities)

### Database & Caching
- SQLite (development)
- MySQL 8.0 (production)
- Redis for caching
- Database queue driver for background jobs

### Testing
- PHPUnit with Laravel plugin
- In-memory SQLite for test isolation

### Code Quality
- Laravel Pint (PHP formatting)
- ESLint 9 + Prettier (JS/TS formatting)
- prettier-plugin-tailwindcss (class sorting)

## Architecture

### Directory Structure
```
app/
├── Http/
│   ├── Controllers/      # Request handlers
│   ├── Middleware/
│   └── Requests/         # Form request validation
├── Jobs/                 # Background jobs (Gmail sync)
├── Models/               # Eloquent models
├── Notifications/        # User notifications
├── Providers/            # Service providers
└── Services/             # Business logic (FulfilService, GmailService)

resources/js/
├── Components/           # Reusable React components
├── Layouts/              # Page layouts
├── Pages/                # Inertia page components
│   ├── ActiveCustomers/
│   ├── Prospects/
│   ├── AccountsReceivable/
│   ├── Gmail/
│   └── Admin/
└── types/                # TypeScript definitions
```

### Key Services
- **FulfilService** (`app/Services/FulfilService.php`): All Fulfil ERP API interactions
- **GmailService** (`app/Services/GmailService.php`): Gmail OAuth and email sync

### Background Jobs
- `SyncGmailForUser`: Full 365-day email sync for a salesperson
- `SyncGmailForDomains`: Targeted sync for specific domains (on prospect/customer creation)
- `SyncGmailForAllUsers`: Admin-triggered sync for all salespersons
- Queue driver: `database` (configured via `QUEUE_CONNECTION`)
- Dev server runs `php artisan queue:listen` via `composer run dev`
- Scheduled command: `gmail:sync` runs every 15 minutes

## Coding Standards

### PHP

- **Always use `use` statements** at the top of PHP files. Never use fully-qualified class names inline.
- Use PHP 8 constructor property promotion in `__construct()`.
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.
- Prefer PHPDoc blocks over inline comments.

### Frontend HTTP Requests

Always use `axios` for HTTP requests in React/TypeScript code. Never use the native `fetch()` API. Axios is configured with automatic CSRF token handling via the `XSRF-TOKEN` cookie.

### Database

- Prefer `Model::query()` over `DB::` facade for queries.
- Use Eloquent relationships over raw queries or manual joins.
- Use eager loading to prevent N+1 query problems.
- Use Laravel's query builder only for very complex operations that don't fit Eloquent well.

### Scaffolding

Use `php artisan make:*` commands (with `--no-interaction`) to create new files (migrations, controllers, models, etc.) instead of hand-creating them.

### Formatting

Run `vendor/bin/pint --dirty` before finalizing PHP changes to ensure code matches the project's style.

### Testing

Every change should be tested. Run the minimum needed tests with `php artisan test --compact` and use `--filter` to target specific tests.

## External Integrations

### Fulfil ERP
- Sandbox and Production environments supported
- Environment variables: `FULFIL_SANDBOX_SUBDOMAIN`, `FULFIL_SANDBOX_TOKEN`, `FULFIL_PRODUCTION_SUBDOMAIN`, `FULFIL_PRODUCTION_TOKEN`, `FULFIL_DEFAULT_ENV`
- **Authentication**: Personal Access Tokens via `X-API-KEY` header
- **Base URL**: `https://{subdomain}.fulfil.io/api/v2/`
- **Important**: GET endpoints return only `id` and `rec_name` by default - must specify `fields` parameter

#### Key Models Used
| Model | Purpose |
|-------|---------|
| `party.party` | Customers/Contacts |
| `party.contact_mechanism` | Contact emails, phones, custom data fields |
| `party.category` | Categories including shipping terms |
| `account.invoice` | Invoices for AR tracking |
| `account.invoice.payment_term` | Payment terms (Net 30, etc.) |
| `product.price_list` | Price lists for discount extraction |

#### Contact Mechanism Patterns
- **Department contacts**: `name` = "Buyer: John Smith", `value` = email
- **Broker contacts**: `name` = "Broker (Company): Contact Name", `value` = email
- **Data fields**: `name` = "data", `value` = "shelf_life_req:180"

#### Channel Filter (B2B)
- Channel ID: `19` / Code: `RTL1` / Name: `Retail Channel`

#### Account Filter (AR - B2B)
- Account ID: `128` (Accounts Receivable - B2B)

### Gmail Integration
- OAuth 2.0 via Google Cloud Console
- Environment variables: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`
- Scopes: `gmail.readonly`, `userinfo.email`
- Syncs emails matching customer/prospect domains
- Stores sync history in `gmail_sync_history` table

### Authentication
- Google OAuth via Socialite
- Domain restriction via `ALLOWED_AUTH_DOMAINS`
- Email whitelist via `ALLOWED_AUTH_EMAILS`
- Admin assignment via `ADMIN_EMAILS`

## Development Setup
1. Copy `.env.example` to `.env` and configure environment variables
2. `composer install && npm install`
3. `php artisan migrate`
4. `composer run dev` (runs server + queue + logs + vite concurrently)

## Production Deployment
- **Hosting**: Laravel Forge
- **Database**: MySQL 8.0
- **Queue**: Database driver
- **Deploy**: Automatic on push to main branch
- **Deploy Script**:
```bash
$CREATE_RELEASE()

cd $FORGE_RELEASE_DIRECTORY

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader
$FORGE_PHP artisan optimize
$FORGE_PHP artisan storage:link
$FORGE_PHP artisan migrate --force

npm ci || npm install
npm run build

$ACTIVATE_RELEASE()

$RESTART_QUEUES()
```

Migrations run automatically during deployment. No manual steps needed after merging PRs.

## Commands
```bash
npm run dev           # Vite dev server
npm run build         # Production build
npm run lint          # ESLint
npm run format        # Prettier
php artisan test      # Run tests
vendor/bin/pint       # PHP formatting
composer run dev      # Full dev environment
```

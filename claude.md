# Sales Application

B2B sales tool suite integrating with Fulfil ERP for customer data and Gmail for email tracking.

## Git

- Default branch: `main`
- PRs should target `main`

## Tech Stack

- **Backend**: PHP 8.2+, Laravel 12.x, Inertia.js 2.0
- **Frontend**: React 19, TypeScript 5.7+, Tailwind CSS 4.x, Radix UI
- **Database**: SQLite (dev), MySQL 8.0 (production), Redis for caching
- **Queue**: Database driver
- **Testing**: PHPUnit with in-memory SQLite
- **Code Quality**: Laravel Pint (PHP), ESLint + Prettier (JS/TS)

## Key Services

- **FulfilService** (`app/Services/FulfilService.php`): All Fulfil ERP API interactions
- **GmailService** (`app/Services/GmailService.php`): Gmail OAuth and email sync

## User Roles

Admin (full access + user management), Salesperson (prospects + customers + Gmail), User (read-only).

## External Integrations

- **Fulfil ERP**: Customer data, invoices, pricing (see `docs/fulfil-api.md`)
- **Gmail API**: Email sync for salesperson-customer communication
- **Google OAuth**: Authentication via Socialite, restricted by domain/email whitelist

## Commands

```bash
composer run dev              # Full dev environment (server + queue + logs + Vite)
php artisan test --compact    # Run tests (use --filter to target specific tests)
vendor/bin/pint --dirty       # Format PHP before finalizing changes
npm run build                 # Production frontend build
npm run lint                  # ESLint
npm run format                # Prettier
```

## Universal Rules

- **Always use axios** for HTTP requests in React/TypeScript. Never use `fetch()`. Axios is configured with automatic CSRF token handling via the `XSRF-TOKEN` cookie.
- **MySQL index name limit**: MySQL enforces a 64-character limit on index/key names. When adding composite indexes or unique constraints on tables with long names, always pass an explicit shorter name as the second argument (e.g., `$table->unique(['col_a', 'col_b'], 'short_custom_name')`). SQLite does not enforce this, so it will not be caught locally.
- **Every change should be tested.** Run the minimum needed tests with `--filter` to target specific tests.
- **Use `php artisan make:*`** commands (with `--no-interaction`) to scaffold new files instead of hand-creating them.
- **Run `vendor/bin/pint --dirty`** before finalizing PHP changes.

## Development Setup

1. Copy `.env.example` to `.env` and configure environment variables
2. `composer install && npm install`
3. `php artisan migrate`
4. `composer run dev`

## Additional Context

| Topic              | File                         |
| ------------------ | ---------------------------- |
| Fulfil ERP API     | `docs/fulfil-api.md`         |
| Gmail Integration  | `docs/gmail-integration.md`  |
| Authentication     | `docs/authentication.md`     |
| Coding Standards   | `docs/coding-standards.md`   |
| Server & Deploy    | `docs/server.md`             |
| AR Automation      | `docs/ar-automation/`        |

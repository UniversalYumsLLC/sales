# Gmail Integration

## OAuth Configuration

- OAuth 2.0 via Google Cloud Console
- Environment variables: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`
- Scopes: `gmail.readonly`, `userinfo.email`

## How It Works

- Syncs emails matching customer/prospect domains
- Stores sync history in `gmail_sync_history` table
- Sync interval configurable via `GMAIL_SYNC_INTERVAL` (default: 15 minutes)
- Sync overlap configurable via `GMAIL_SYNC_OVERLAP` (default: 2 minutes)

## Background Jobs

- `SyncGmailForUser`: Full 365-day email sync for a salesperson
- `SyncGmailForDomains`: Targeted sync for specific domains (triggered on prospect/customer creation)
- `SyncGmailForAllUsers`: Admin-triggered sync for all salespersons
- Queue driver: `database` (configured via `QUEUE_CONNECTION`)
- Scheduled command: `gmail:sync` runs every 15 minutes

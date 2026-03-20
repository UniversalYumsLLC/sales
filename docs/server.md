# Server & Deployment

## Hosting

- **Provider:** Digital Ocean
- **Management:** Laravel Forge — https://forge.laravel.com/yums
- **Server:** `forge@167.99.234.32` (shared with PIM)

## SSH Access

```bash
ssh forge@167.99.234.32
```

Or use the shell alias:

```bash
forge
```

The application is located at `/home/forge/sales.yums.com/current`.

## Directory Structure

```
/home/forge/sales.yums.com/
├── .env                    # Environment configuration (persistent)
├── current -> releases/... # Symlink to active release
├── releases/               # Deployment releases (Forge keeps last 4)
└── storage/                # Persistent storage across deployments
    ├── app/
    ├── framework/
    └── logs/
        └── laravel.log
```

## Deployments

Auto-deploy is enabled. When code is merged to `main`, Forge automatically:

1. Creates a new release directory
2. Clones the repository
3. Runs `composer install` and `npm run build`
4. Runs migrations
5. Updates the `current` symlink to the new release
6. Restarts PHP-FPM and queue workers

The `.env` and `storage/` directories are persistent and shared across all releases.

### Deploy Script

The following is the Forge deploy script (Forge lifecycle hooks like `$CREATE_RELEASE()` are Forge-specific macros):

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

# Warm Fulfil cache in background to prevent cold-start timeouts
$FORGE_PHP artisan fulfil:warm-cache >> /dev/null 2>&1 &
```

Migrations run automatically during deployment. No manual steps needed after merging PRs.

## Scheduled Jobs

Ensure `php artisan schedule:run` is configured in Forge's scheduler (runs every minute).

Key scheduled task: `gmail:sync` runs every 15 minutes.

## Environment Variables

Managed via Forge: https://forge.laravel.com/yums/exquisite-data-v2/3082011/environment

## Common Commands

```bash
# SSH into the server
ssh forge@167.99.234.32

# Navigate to the application
cd ~/sales.yums.com/current

# View application logs
tail -f ~/sales.yums.com/storage/logs/laravel.log

# Run artisan commands
php artisan tinker
php artisan queue:work --once
php artisan migrate:status

# Check environment
php artisan env
```

## Troubleshooting

### Viewing Logs

```bash
# Recent errors
tail -100 ~/sales.yums.com/storage/logs/laravel.log

# Follow logs in real-time
tail -f ~/sales.yums.com/storage/logs/laravel.log
```

### Queue Issues

```bash
cd ~/sales.yums.com/current

# Process a single job manually
php artisan queue:work --once

# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

### Cache Issues

```bash
cd ~/sales.yums.com/current

# Clear all caches
php artisan optimize:clear
```

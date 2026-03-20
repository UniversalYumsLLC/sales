# Production Deployment

## Hosting

- **Platform**: Laravel Forge
- **URL**: sales.yums.com
- **Database**: MySQL 8.0
- **Queue**: Database driver with daemon worker
- **Auto-deploy**: Pushes to `main` trigger automatic deployment

## Deploy Script

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

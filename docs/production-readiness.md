# Guestory Production Readiness

This runbook is the deployment checklist for the Guestory MVP.

## Runtime Shape

- Frontend: React/Vite build served by PM2 preview on `127.0.0.1:5177`.
- Backend: Laravel API served by PHP-FPM through Nginx from `apps/api/public`.
- Database: PostgreSQL.
- Queue: optional PM2 worker for Laravel queue jobs.
- Photos: local Laravel public storage, exposed through `/storage`.

Do not run `php artisan serve` in production. It remains in PM2 only for local/staging development.

## Required Server Environment

Backend `.env` values:

```env
APP_NAME=Guestory
APP_ENV=production
APP_DEBUG=false
APP_URL=https://guestory.example.com

DB_CONNECTION=pgsql
DB_URL=postgresql://USER:PASSWORD@127.0.0.1:5432/guestory
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database

FILESYSTEM_DISK=public

WAPI_BASE_URL=https://wapi.proyek.org
WAPI_API_KEY=
WAPI_NUMBER_ID=
WAPI_TIMEOUT=10
WAPI_DRY_RUN=false
```

Frontend build env:

```env
VITE_API_BASE_URL=/api
```

Keep real DB and WAPI credentials in server env/secret storage only.

## First Deploy

```sh
composer --working-dir=apps/api install --no-dev --optimize-autoloader
npm ci
npm --prefix apps/web ci
npm run build:web

cd apps/api
php artisan key:generate --force
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan view:cache
cd ../..

GUESTORY_PM2_MODE=production pm2 start ecosystem.config.cjs
pm2 save
```

## Update Deploy

```sh
npm ci
npm --prefix apps/web ci
npm run build:web
composer --working-dir=apps/api install --no-dev --optimize-autoloader
cd apps/api
php artisan migrate --force
php artisan storage:link --force
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
cd ../..
GUESTORY_PM2_MODE=production pm2 reload ecosystem.config.cjs --update-env
```

Route cache is intentionally skipped for now because the MVP API still uses closure routes. Enable `php artisan route:cache` only after those routes are moved into controller classes.

## Health Checks

- API health: `GET /api/health`
- Laravel framework health: `GET /up`
- Frontend: `GET /admin`
- Storage: confirm `apps/api/public/storage` resolves to the current release's `storage/app/public`, then verify an uploaded photo URL under `/storage/...` returns HTTP 200. Never copy a storage symlink from a different workspace or release path.

## Nginx

Use `deploy/nginx/guestory.conf.example` as the starting point. It proxies the frontend to PM2 and sends `/api`, `/up`, and `/storage` to Laravel/PHP-FPM.

For domain setup, DNS, and SSL steps, use `docs/domain-setup.md`. The helper script `deploy/scripts/render-nginx.sh` renders the Nginx template with the chosen domain, app root, and PHP-FPM socket.

## Local Production Smoke

```sh
php apps/api/artisan test
npm run lint:web
npm run build:web
php apps/api/artisan route:list --path=api
```

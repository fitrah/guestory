# Guestory Domain Setup

Use this checklist when the final Guestory domain is known.

## Inputs

- Domain: `guestory.example.com`
- App root on server: `/var/www/guestory`
- PHP-FPM socket: `/run/php/php8.3-fpm.sock`
- Frontend PM2 port: `127.0.0.1:5177`

Keep real credentials in server env/secret storage only. Do not paste DB, WAPI, or app secrets into chat or command history.

## DNS

Create these records at the DNS provider:

```text
A      guestory.example.com      <SERVER_PUBLIC_IPV4>
AAAA   guestory.example.com      <SERVER_PUBLIC_IPV6>   optional
```

If using a `www` alias:

```text
CNAME  www.guestory.example.com  guestory.example.com
```

Wait until DNS resolves before requesting SSL:

```sh
dig +short guestory.example.com
curl -I http://guestory.example.com
```

## Server Nginx Site

Render the Nginx config from the repo template:

```sh
cd /var/www/guestory
deploy/scripts/render-nginx.sh guestory.example.com /var/www/guestory /run/php/php8.3-fpm.sock \
  | sudo tee /etc/nginx/sites-available/guestory.conf >/dev/null
sudo ln -sfn /etc/nginx/sites-available/guestory.conf /etc/nginx/sites-enabled/guestory.conf
sudo nginx -t
sudo systemctl reload nginx
```

The generated config routes:

- `/` to the frontend PM2 preview server
- `/api` to Laravel/PHP-FPM
- `/up` to Laravel/PHP-FPM
- `/storage` to Laravel public storage

## Laravel Env

Set the production URL:

```env
APP_URL=https://guestory.example.com
```

If the frontend is built on the server, keep:

```env
VITE_API_BASE_URL=/api
```

Then refresh caches:

```sh
cd /var/www/guestory
npm run prod:clear
npm run prod:build
```

## SSL

After DNS points to the server and the HTTP site responds, request SSL:

```sh
sudo certbot --nginx -d guestory.example.com
sudo nginx -t
sudo systemctl reload nginx
```

Verify automatic renewal:

```sh
sudo certbot renew --dry-run
```

## PM2

Run production PM2 mode:

```sh
cd /var/www/guestory
GUESTORY_PM2_MODE=production pm2 start ecosystem.config.cjs
pm2 save
```

For later deploys:

```sh
cd /var/www/guestory
npm run prod:build
GUESTORY_PM2_MODE=production pm2 reload ecosystem.config.cjs --update-env
```

## Verification

```sh
curl -fsS https://guestory.example.com/api/health
curl -I https://guestory.example.com/admin
curl -I https://guestory.example.com/invite/demo-budi
```

Expected:

- `/api/health` returns `status: ok` and `checks.database: ok`
- `/admin` returns frontend HTML
- `/invite/...` returns frontend HTML and loads invitation data through `/api`

## Notes

- Do not run `php artisan serve` for production traffic.
- `route:cache` is intentionally skipped until API closure routes are moved into controllers.
- Keep `client_max_body_size` at least `8m` because guest photo upload allows files up to 5 MB.

# Deploying with MySQL

DigSignage can use MySQL in production while local development and tests continue
to use SQLite. The database driver comes from the server's `.env`, not from the
SQLite file in a developer checkout. Do not commit a production `.env`.

## Server requirements

- A PHP version compatible with the deployed `composer.lock` (currently PHP
  8.4.1 or newer), including `pdo_mysql` and the extensions required by Composer.
- MySQL 8.0 or newer (or a supported MariaDB release), with an existing database
  and a user permitted to create and alter its tables.
- Composer dependencies installed, built frontend assets, and a web server whose
  document root is the application's `public` directory.

In the production `.env`, set at least:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.com
INITIAL_ADMIN_SETUP_KEY=replace-with-a-long-random-one-time-key
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=digsignage
DB_USERNAME=digsignage
DB_PASSWORD=replace-with-server-secret
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_SECURE_COOKIE=true
```

Replace the example values with the server's real settings. If `DB_URL` is set,
remove it or make sure it also points to MySQL: it overrides the individual
`DB_*` connection fields. Keep the existing `APP_KEY` on an established
installation; generating a new one can invalidate sessions and encrypted data.
If MySQL requires TLS, set `MYSQL_ATTR_SSL_CA` to the CA file path on the server.

After installing dependencies and setting `.env`, run these commands from the
application directory:

```bash
php -m | grep -i pdo_mysql
php artisan config:clear
php artisan migrate:status
php artisan migrate --force
php artisan config:cache
```

The first visit to Templates installs missing ready-made catalog records in
the production database. It does not run `DatabaseSeeder`, which also creates
a demo administrator. Catalog installation is repeatable and leaves existing
platform and team templates unchanged. To populate optional stock photos and
rendered thumbnails as well, run `php artisan db:seed
--class=CatalogTemplateSeeder --force` during deployment; this separate seeder
downloads images and may take time. Until then, missing catalog photos use a
bundled gradient fallback; the layouts and live widgets are still usable.

On a fresh production database with no platform administrator, the first visit
redirects to the administrator registration form. Set a unique, random
`INITIAL_ADMIN_SETUP_KEY` in the server's private `.env` before visiting it;
the form requires this key. Do not put the key in a URL or commit it. After the
administrator account is created, remove the key from `.env` and rerun
`php artisan config:cache`. Normal registration then resumes. Existing
installations with a platform administrator skip this first-run flow.
Generate a suitable key on the server with `openssl rand -hex 32` and keep it
private.

Back up an existing production database before running migrations. Migrations
create the schema in MySQL; they do **not** copy existing SQLite records into
MySQL. If those records matter, plan a separate data migration before switching
traffic. Do not run `migrate:fresh` against a production database.

## Live screens and queue calls with Reverb

The app broadcasts queue calls to the relevant queue-display players and
screen manifest edits to the paired player. Players subscribe on their private
`player.{device_uuid}` channel and refresh immediately; manifest polling stays
available if WebSockets disconnect. In production, set these environment
variables in the hosting panel (use unique generated credentials):

```dotenv
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=digsignage
REVERB_APP_KEY=replace-with-random-public-app-key
REVERB_APP_SECRET=replace-with-random-private-app-secret
REVERB_HOST=your-public-websocket-host.example.com
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
```

The host must run `php artisan reverb:start` as a supervised, persistent
process and proxy the public host's WebSocket `/app` and HTTP `/apps` paths to
the local Reverb port with upgrade headers. The browser must be able to reach
that host over WSS and PHP must be able to reach it for publishing. Keep the
secret server-side; only the app key is sent to players. Refresh Laravel's
config cache and restart Reverb after changing these values. A deploy that
does not start Reverb or configure the proxy will continue to work through
REST polling, but will not deliver instant updates.

## If nginx still reports 502

A 502 is a failure between nginx and its upstream, including FastCGI response
header limits; it does not by itself identify a MySQL error. On the server,
inspect the nginx error log,
the configured `fastcgi_pass` socket or port, and the matching PHP-FPM service:

If the log says `upstream sent too big header` for `/login`, deploy the version
that removes the optional unbounded Vite preload `Link` header. This fixes the
application-side header growth without weakening security headers. Do not
confuse `Primary script unknown` requests for non-existent `config.php` files
from scanners with this login failure.

```bash
sudo tail -n 100 /var/log/nginx/error.log
sudo nginx -t
systemctl list-units 'php*-fpm.service'
sudo journalctl -u php8.3-fpm -n 100 --no-pager
```

Substitute the installed PHP-FPM version in the last command. Also check
`storage/logs/laravel.log` for application or database errors. Do not post
passwords, `.env`, or full production logs publicly; redact secrets first.

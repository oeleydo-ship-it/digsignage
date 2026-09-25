# Zero-downtime deployment

DigSignage deploys by building each version in its own folder and switching a
`current` symlink once the new version is ready. Visitors, players and queue
displays keep being served by the old version until the switch, which is a
single atomic rename. The last few releases stay on disk for instant rollback.

There are two ways to ship a version. Both use the same script
(`scripts/deploy/release.sh`), so they behave identically:

- **GitHub Actions**: push a tag such as `v1.4.0`. The `deploy` workflow builds
  the package, publishes a GitHub release with it attached, and deploys it over
  SSH.
- **Super admin → Updates**: install a GitHub release, tag or branch, or upload
  a package `.zip`. Every version's new features are listed there.

## 1. Server layout (one-time)

On an Ubuntu/Debian server with PHP 8.4 (with the `zip` extension), Composer,
Node 22 (only needed for source-only installs), `unzip` and Nginx + PHP-FPM:

```bash
sudo mkdir -p /var/www/digsignage/{releases,shared,packages}
sudo chown -R deploy:www-data /var/www/digsignage
cd /var/www/digsignage
cp /path/to/your/.env shared/.env          # production environment file
mkdir -p shared/storage                    # uploads, logs, sessions, cache
```

```
/var/www/digsignage
├── current -> releases/1.4.0-20261001120000   (web server root is current/public)
├── releases/                                   one folder per version
├── shared/.env                                 shared by every release
├── shared/storage/                             shared by every release
├── shared/deploy.env                           optional deploy settings
└── packages/                                   uploads from GitHub Actions
```

If you use SQLite, keep the database at `shared/database/database.sqlite`; each
release links to it.

### Nginx

Point the site at `current/public` and resolve the symlink per request, so
PHP-FPM and OPcache pick up the new release immediately after the switch:

```nginx
root /var/www/digsignage/current/public;

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    fastcgi_param DOCUMENT_ROOT $realpath_root;
}
```

### Background processes

Run them from `current` so each restart picks up the live release. The deploy
script signals them with `queue:restart`, `schedule:interrupt` and
`reverb:restart`; Supervisor (or systemd) starts them again in the new release.

```ini
[program:digsignage-queue]
command=php /var/www/digsignage/current/artisan queue:work redis --sleep=1 --tries=3
autorestart=true
user=deploy

[program:digsignage-reverb]
command=php /var/www/digsignage/current/artisan reverb:start
autorestart=true
user=deploy
```

Add the scheduler to cron:
`* * * * * php /var/www/digsignage/current/artisan schedule:run >> /dev/null 2>&1`

### Deploy settings

`shared/deploy.env` is read by the deploy scripts on every deploy:

```bash
DEPLOY_PHP=php8.4
DEPLOY_KEEP_RELEASES=5
# Optional: reload PHP-FPM after the switch (grant this in sudoers for the deploy user)
DEPLOY_RELOAD_COMMAND="sudo systemctl reload php8.4-fpm"
```

## 2. In-app updater (Super admin → Updates)

Add to `shared/.env`:

```bash
DEPLOY_UPDATER_ENABLED=true
DEPLOY_BASE_PATH=/var/www/digsignage
DEPLOY_GITHUB_REPOSITORIES=oeleydo-ship-it/digsignage
# Needed for private repositories: fine-grained token, "Contents: read-only"
DEPLOY_GITHUB_TOKEN=
```

The web server user must be able to write to `releases/` and run `php`,
`composer` and `bash`. Installs run in a background process (log:
`shared/storage/logs/releases.log`), so they survive the queue worker restart
the deploy itself triggers. Every install, upload and rollback asks for the
administrator's password and is recorded in the platform audit log.

Uploaded packages must contain `artisan`, `composer.json` and `VERSION` at the
root (or inside one top-level folder, as GitHub source zips do). Packages with
paths that escape the release folder, symbolic links, or more than
`DEPLOY_MAX_EXTRACTED_MB` of content are refused.

## 3. GitHub Actions

In the repository's **Settings → Secrets and variables → Actions**:

| Type | Name | Value |
| --- | --- | --- |
| Variable | `DEPLOY_HOST` | Server hostname or IP. The deploy job is skipped until this is set. |
| Variable | `DEPLOY_USER` | SSH user, e.g. `deploy` |
| Variable | `DEPLOY_PATH` | `/var/www/digsignage` |
| Variable | `DEPLOY_PORT` | Optional, defaults to 22 |
| Variable | `DEPLOY_PHP` | Optional PHP binary, defaults to `php` |
| Secret | `DEPLOY_SSH_KEY` | Private key whose public key is in the server user's `authorized_keys` |
| Secret | `DEPLOY_KNOWN_HOSTS` | Output of `ssh-keyscan -p 22 your.server` |

Protect the `production` environment with required reviewers if deploys should
be approved before they run.

## 4. Shipping a version

1. Add a section to `CHANGELOG.md`. Bullets under `### Added` become the "new
   features" list on the Updates page and in the GitHub release:

   ```markdown
   ## [1.4.0] - 2026-10-01

   ### Added

   - Visitor check-in kiosk
   ```

2. Set `VERSION` to `1.4.0`, commit and push.
3. Tag and push: `git tag v1.4.0 && git push origin v1.4.0`.

The workflow refuses to run when the tag and `VERSION` disagree.

## 5. Migrations without downtime

Migrations run before the switch while the previous version is still serving
traffic, and rollbacks do not reverse them. Keep each release compatible with
the schema of the next one:

- Add tables and nullable (or defaulted) columns freely.
- Rename or drop a column in two releases: first stop using it, then remove it.
- Backfill large tables in a queued job rather than in the migration.

## 6. Rolling back

Use **Roll back** on the Updates page, or on the server:

```bash
bash /var/www/digsignage/current/scripts/deploy/rollback.sh            # previous release
bash /var/www/digsignage/current/scripts/deploy/rollback.sh /var/www/digsignage/releases/1.3.0-20260920090000
```

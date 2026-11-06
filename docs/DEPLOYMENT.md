# Deployment, Backup and Operations

## Requirements
PHP 8.3, Composer, Node 20+, MySQL 8 / PostgreSQL 15+ (SQLite is for local/testing only), a queue worker, a scheduler, and an SMTP provider.

## First deploy
```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env && php artisan key:generate   # then edit .env (below)
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force   # roles/permissions only
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
php artisan storage:link
```
Never run `db:seed` with the demo seeder in production: `DemoDataSeeder` throws outside `local`/`testing`.
Create the first staff user manually (tinker or a one-off command) and assign a role from `RolesAndPermissionsSeeder`.

Every release: `php artisan down`, deploy, `php artisan migrate --force`, re-cache, `php artisan queue:restart`, `php artisan up`.

## Processes
- Queue worker (supervisor): `php artisan queue:work --tries=3 --max-time=3600`
- Scheduler (cron): `* * * * * php /path/artisan schedule:run`
- Web server document root: `public/`; enforce HTTPS.

## Environment variables
| Variable | Production value / note |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` (never true in production) |
| `APP_KEY` | generated; keep secret, back up securely |
| `APP_URL` | public HTTPS URL |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | production database; least-privilege user |
| `SESSION_DRIVER` | `database` or `redis`; set `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true` |
| `SESSION_DOMAIN` | your domain |
| `CACHE_STORE`, `QUEUE_CONNECTION` | `redis` or `database` |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | real provider (default `log` sends nothing) |
| `LOG_CHANNEL`, `LOG_LEVEL` | `stack`/`daily`, `warning` or higher |

## Integrations still awaiting provider selection
- **Banking / payment rails:** none connected. Transfers are requests that staff approve and process; no external payment success is ever fabricated. Inbound deposits are verified manually by staff.
- **Mail provider** (verification, password reset, notifications): choose and configure SMTP/API.
- **Error monitoring, log shipping, uptime checks:** not configured.
- **Object storage / off-site backup target:** not configured.

## Backups
- Database is the system of record (the ledger is append-only, protected by DB triggers). Take a full logical dump at least daily plus point-in-time recovery (binlog/WAL) where available; retain 30 days, keep one copy off-site and encrypted.
- Back up `.env`/secrets (including `APP_KEY`) separately in a secrets manager.
- Test restores quarterly: restore into a scratch database, run `php artisan migrate --pretend`, and verify ledger totals per currency match account balances.
- Never "fix" a balance by editing rows: post an adjusting/reversing ledger entry through the application so it is audited.

## Operational notes
- Admin panel at `/admin`; privileged actions require permission and a reason, and write audit events.
- Customer login is throttled (5/min per email+IP). Staff should enable two-factor authentication.
- Logs must not contain passwords, tokens or full account numbers; do not add request-body logging.
- Rollback: redeploy the previous release; migrations are forward-only, so restore from backup if a migration must be undone.

## Pre-release checklist
- [ ] `php artisan test` green; `vendor/bin/phpstan analyse`; `vendor/bin/pint`
- [ ] `APP_DEBUG=false`, HTTPS, secure cookies
- [ ] Migrations run on a clean database
- [ ] Queue worker and scheduler running
- [ ] Mail delivery verified
- [ ] Backup job running and a restore tested
- [ ] First staff account created with 2FA

# cPanel Staging Deployment Guide

**Last updated:** 2026-06-15  
**Application:** AI Counsellor SaaS (Laravel 13, PHP 8.3+)  
**Scope:** First staging deployment on cPanel. Same codebase is portable to VPS/cloud later.

## Prerequisites (cPanel features)

| Feature | Required | Notes |
|---------|----------|-------|
| PHP **8.3+** | Yes | Select in MultiPHP Manager |
| PHP extensions | Yes | `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `tokenizer`, `xml`, `zip` |
| MySQL / MariaDB | Yes | Dedicated database + user |
| SSL (AutoSSL or manual) | Yes | HTTPS for `APP_URL`, sessions, webhooks |
| SSH access | Strongly recommended | For Composer and Artisan |
| Cron jobs | Yes | Laravel scheduler (and optional queue drain) |
| Composer | Yes | Via SSH (`composer` or `php composer.phar`) |
| Node.js | For build step | Run `npm ci && npm run build` on deploy machine if cPanel Node is unavailable |

See also [CPANEL_LIMITATIONS.md](../setup/CPANEL_LIMITATIONS.md) for capability limits.

## Recommended subdomain setup

1. Create subdomain, e.g. `staging.yourdomain.com`.
2. Point document root to Laravel **`public`** directory — not the project root.

```
/home/USERNAME/ai_counsellor/public
```

If cPanel only allows document root under `public_html`, use a symlink or cPanel “document root” override so requests never serve `.env`, `storage/`, or `vendor/` directly.

3. Enable SSL for the subdomain before going live.

## Deploy the code

### Option A — Git clone (recommended)

```bash
cd ~
git clone https://github.com/sp7037/ai_counsellor.git
cd ai_counsellor
git checkout master
```

### Option B — Zip upload

Upload and extract outside `public_html`, keeping `public/` as the only web-exposed folder.

## Environment file

1. Copy template:

```bash
cp .env.example .env
```

2. Edit `.env` in the project root (never inside `public/`).

Minimum staging values:

```dotenv
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://staging.yourdomain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

FAKE_AI_ENABLED=false
FAKE_PAYMENT_ENABLED=false
FAKE_MESSAGING_ENABLED=false

RAZORPAY_ENABLED=true
PAYMENT_ENVIRONMENT=test
# ... Razorpay test keys from dashboard
```

3. Generate application key:

```bash
php artisan key:generate
```

## Install dependencies

From project root via SSH:

```bash
composer install --no-dev --optimize-autoloader
```

### Frontend assets

Build on a machine with Node **22.12+** (or use cPanel Node if available):

```bash
npm ci
npm run build
```

Commit or upload the generated `public/build/` directory if you cannot run Node on the server.

## Laravel setup commands

Run from project root:

```bash
php artisan migrate --force
php artisan storage:link
php artisan db:seed --class=PlansSeeder
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Create platform super admin (local-only command is blocked in production — use the standard platform admin creation flow documented in `LOCAL_SETUP.md` or run `php artisan platform:create-super-admin` if available in your environment).

## File permissions

```bash
chmod -R ug+rwx storage bootstrap/cache
```

Directories must be writable by the PHP/web user. On some hosts:

```bash
find storage bootstrap/cache -type d -exec chmod 775 {} \;
find storage bootstrap/cache -type f -exec chmod 664 {} \;
```

## Cron — Laravel scheduler

Add cPanel cron job (**every minute**):

```cron
* * * * * /usr/local/bin/php /home/USERNAME/ai_counsellor/artisan schedule:run >> /dev/null 2>&1
```

Adjust PHP binary path (`which php` via SSH).

Scheduled tasks include:

- `subscriptions:maintain` (daily)
- `payments:reconcile` (hourly)

## Queue mode on cPanel staging

**Recommended for staging:** `QUEUE_CONNECTION=database` or `sync`.

The application does not require persistent workers for core HTTP flows (widget, webhooks, messaging process synchronously). For occasional background jobs, add optional cron every 5 minutes:

```cron
*/5 * * * * /usr/local/bin/php /home/USERNAME/ai_counsellor/artisan queue:work --stop-when-empty --max-time=240 >> /dev/null 2>&1
```

**Future VPS:** use Supervisor with long-running `queue:work` and optional Redis — see [VPS_PRODUCTION_DEPLOYMENT.md](VPS_PRODUCTION_DEPLOYMENT.md).

## Webhook URLs (configure in provider dashboards)

| Provider | URL |
|----------|-----|
| Razorpay (test) | `https://staging.yourdomain.com/webhooks/payments/razorpay` |
| Meta WhatsApp | `https://staging.yourdomain.com/webhooks/messaging/meta` |

Use HTTPS only. Match secrets in `.env` with provider dashboard values.

## Health check

Laravel built-in endpoint (no secrets):

```
GET https://staging.yourdomain.com/up
```

Expect HTTP 200 when the application boots. Platform admins can use **System Health** after login.

## Staging smoke tests

| Area | How to verify |
|------|----------------|
| Login | Platform super admin + tenant owner accounts |
| Tenant creation | Platform → Tenants → Create |
| Widget | Tenant → Widget → create key; load `public/widget-demo/` or tenant site |
| AI | Configure platform/tenant AI; send widget message (OpenAI test key) |
| Leads | Widget lead capture or tenant lead create |
| Payments | Razorpay **test mode** checkout on purchasable plan |
| WhatsApp | Tenant → Integrations → WhatsApp; Meta test app OR enable `FAKE_MESSAGING_ENABLED=true` only for internal webhook tests |
| Integrations | Platform → Integrations index |

## Rollback

1. Put app in maintenance mode: `php artisan down`
2. Restore database backup from pre-deploy snapshot
3. `git checkout <previous-tag-or-commit>` (or restore previous zip)
4. `composer install --no-dev --optimize-autoloader`
5. `php artisan migrate --force` (only if migrations are backward-compatible; otherwise restore DB instead)
6. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
7. `php artisan up`

Keep previous `public/build/` if frontend changed.

## Common mistakes

| Mistake | Fix |
|---------|-----|
| Document root = project root | Point to `public/` only |
| `APP_DEBUG=true` on staging | Set `false`; use logs |
| Missing `storage:link` | Run `php artisan storage:link` |
| Cached config after `.env` change | `php artisan config:clear` then recache |
| Webhooks over HTTP | Require HTTPS |

## Security reminder

- Never commit `.env`
- Never expose `vendor/`, `storage/`, or `.git` via web
- Use Razorpay **test** keys on staging
- Rotate keys if accidentally logged

# Deployment Checklist

**Last updated:** 2026-06-15  
Use before and after every staging or production deploy.

## Pre-deployment

- [ ] **Backup** database and `storage/app` (if any uploads exist)
- [ ] **Git status clean** on deploy commit (`git status --short` empty)
- [ ] **Tests pass** locally or in CI: `php artisan test`
- [ ] **Pint pass**: `vendor/bin/pint --test`
- [ ] **Frontend build** (if UI/assets changed): `npm ci && npm run build`
- [ ] **Composer audit**: `composer audit` — 0 advisories target
- [ ] **npm audit**: `npm audit` — 0 vulnerabilities target
- [ ] **`.env` reviewed** — no real secrets in Git; placeholders only in `.env.example`
- [ ] **`APP_DEBUG=false`** for staging/production
- [ ] **`APP_URL`** matches HTTPS domain exactly
- [ ] **Fake providers disabled** (`FAKE_AI_ENABLED`, `FAKE_PAYMENT_ENABLED`, `FAKE_MESSAGING_ENABLED` = false) except deliberate internal tests
- [ ] **Document root** = Laravel `public/` directory

## Deploy commands

- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `php artisan key:generate` (first deploy only)
- [ ] `php artisan migrate --force`
- [ ] `php artisan storage:link`
- [ ] `php artisan db:seed --class=PlansSeeder` (first deploy or when plans change)
- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] `php artisan view:cache`
- [ ] Cron: `* * * * * php artisan schedule:run`
- [ ] Permissions: `storage/` and `bootstrap/cache/` writable

## SSL and networking

- [ ] **SSL active** (HTTPS loads without certificate warnings)
- [ ] **Webhook URLs** use HTTPS:
  - `/webhooks/payments/razorpay`
  - `/webhooks/messaging/meta`
- [ ] **`SESSION_SECURE_COOKIE=true`** when using HTTPS (recommended)

## Functional verification

- [ ] **Health endpoint**: `GET /up` returns 200
- [ ] **Admin login** — platform super admin
- [ ] **Tenant creation** — platform control plane
- [ ] **Tenant owner login** and dashboard
- [ ] **Widget JS** loads from `/build/widget.js`
- [ ] **Widget session** — `POST /widget/v1/session` succeeds from allowed origin
- [ ] **AI response** — widget message with configured OpenAI (or platform-managed) key
- [ ] **Lead capture** — widget or tenant lead create
- [ ] **Razorpay test mode** — checkout on purchasable plan (staging)
- [ ] **WhatsApp** — tenant integration page loads; Meta webhook verify OR fake provider for internal test only
- [ ] **Platform integrations** page loads (`/platform/integrations`)
- [ ] **Payments module** — existing behaviour unchanged (regression spot-check)

## Security verification

- [ ] **Logs** — grep recent log file; no API keys, tokens, or webhook secrets in plain text
- [ ] **`.env` not web-accessible** (try `/../.env` — must 404/403)
- [ ] **`vendor/` not web-accessible**
- [ ] **Error pages** do not expose stack traces (`APP_DEBUG=false`)
- [ ] **Platform super admin** cannot access routine tenant conversation content cross-tenant (spot-check policy)

## Post-deployment

- [ ] Record deploy **commit hash** and timestamp
- [ ] Monitor `storage/logs/laravel.log` for 24h
- [ ] **Rollback plan** documented and tested (see [CPANEL_STAGING_DEPLOYMENT.md](CPANEL_STAGING_DEPLOYMENT.md))

## Rollback readiness

- [ ] Previous release tag/commit noted
- [ ] Database backup from before migration available
- [ ] `php artisan down` tested or documented for maintenance window

## Module scope confirmation

- [ ] **Module 12 (Email) not started** — mail remains `log` or external SMTP only
- [ ] **Module 10 payment integrity** preserved — no checkout/webhook regressions

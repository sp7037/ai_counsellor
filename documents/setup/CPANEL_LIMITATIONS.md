# cPanel Limitations

**Last updated:** 2026-06-15 (Phase 0B — blocked)

## PHP version note

cPanel hosts often run PHP 8.1–8.3. After Phase 0B, the application will target **PHP 8.3+** and **Laravel 12**. Verify host PHP version before any cPanel deployment. Laravel 9 (current interim stack) must not be deployed to production.

## Position in deployment strategy

cPanel-compatible deployment is a **secondary, limited** target. Primary production is VPS/cloud (see [VPS_PRODUCTION_REQUIREMENTS.md](VPS_PRODUCTION_REQUIREMENTS.md)).

The master architecture states: *"No guarantee of persistent workers or WebSockets"* on cPanel.

## What typically works on cPanel

| Capability | cPanel feasibility |
|------------|-------------------|
| HTTP dashboard and API | Yes — document root points to `public/` |
| MySQL / MariaDB | Yes — shared or dedicated database |
| Laravel scheduler | Partial — via cron job every minute |
| File-based cache and sessions | Yes |
| SMTP email (outbound) | Yes — via cPanel mail or external SMTP |
| Static widget CDN assets | Yes — if served from subdomain or CDN |
| HTTPS | Yes — AutoSSL or manual certificate |

## What is limited or unavailable

| Capability | Limitation |
|------------|------------|
| Persistent queue workers | No long-running `queue:work` processes; use `queue:work --stop-when-empty` via cron or database queue with frequent cron |
| Laravel Reverb / WebSockets | Generally unavailable; agent live dashboard requires polling fallback |
| Redis | Often unavailable on shared hosting; fall back to database cache/queue |
| Supervisor / systemd | Not available; no reliable background worker supervision |
| Object storage (S3) | Available via SDK if outbound HTTPS permitted |
| PHP version | Must meet Laravel minimum; may lag behind VPS |
| Memory and execution time | Shared limits may affect AI orchestration jobs |
| Custom ports | WebSocket ports typically blocked |

## AI Counsellor implications

### Required fallbacks for cPanel mode

1. **Real-time agent dashboard** — HTTP polling or Server-Sent Events instead of WebSockets.
2. **Queues** — Database driver with cron-triggered workers; expect higher latency.
3. **Cache** — Database or file cache instead of Redis.
4. **Widget gateway** — Must remain stateless HTTP; no dependency on persistent connections.
5. **AI calls** — Execute synchronously or via cron-processed jobs with strict timeouts.

### Features that must not be cPanel-dependent

- Tenant suspension (must work via HTTP + database state)
- Lead capture and offline widget form (architecture: safe degradation)
- Subscription expiry checks (scheduler via cron)

## Recommended cPanel configuration

```
Document root: /home/user/ai_counsellor/public
PHP version: 8.1+ (upgrade from 8.0 when host supports it)
Cron: * * * * * php /home/user/ai_counsellor/artisan schedule:run
Optional cron: */5 * * * * php /home/user/ai_counsellor/artisan queue:work --stop-when-empty --max-time=240
```

## When to choose VPS instead

Choose VPS/cloud when the tenant requires:

- Live agent dashboard with sub-second updates
- High-volume AI job processing
- Redis-backed rate limiting and sessions
- WebSocket presence and typing indicators
- Reliable queue workers with retry and idempotency at scale

## Documentation obligation

Any module using Redis, Reverb, or persistent workers must document a cPanel-compatible fallback in its completion report.

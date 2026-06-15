# VPS Production Requirements

**Last updated:** 2026-06-15 (Phase 0B — blocked)

## PHP version requirement (updated)

| Environment | PHP requirement |
|-------------|-----------------|
| Local (after Phase 0B) | **8.3+** |
| Production VPS | **8.3+** (8.2 minimum for Laravel 11; Laravel 12 preferred) |
| Current interim stack | 8.0.30 — **not production-ready** |

## Primary production target

AI Counsellor is designed for **managed VPS or cloud VM/container** deployment. This is the reference production environment per [AI_COUNSELLOR_MASTER_ARCHITECTURE.docx](../architecture/AI_COUNSELLOR_MASTER_ARCHITECTURE.docx) §15.

## Recommended stack

| Service | Requirement |
|---------|-------------|
| Web server | Nginx (preferred) or Apache with `public/` document root |
| PHP | **8.3+** required (Laravel 12 target after Phase 0B) |
| PHP-FPM | Required for Nginx |
| Database | MySQL 8+ or managed compatible MySQL |
| Cache / queues | Redis 6+ |
| Queue workers | Supervisor or systemd managing `php artisan queue:work` |
| Scheduler | Cron: `* * * * * php /path/to/artisan schedule:run` |
| Real-time | Laravel Reverb or configurable WebSocket fallback |
| Object storage | S3-compatible (documents, exports, tenant assets) |
| TLS | HTTPS everywhere; valid certificate (Let's Encrypt or managed) |
| Monitoring | Central error tracking, uptime checks, log aggregation |

## Environment configuration

| Setting | Production value |
|---------|------------------|
| `APP_ENV` | production |
| `APP_DEBUG` | false |
| `LOG_LEVEL` | warning or error |
| Session cookies | secure, HTTP-only, SameSite |
| `APP_URL` | https://your-domain.com |

Secrets via environment variables or sealed secret manager — never in Git.

## Process architecture

```
                    ┌─────────────┐
   Internet ───────►│   Nginx     │
                    │  (HTTPS)    │
                    └──────┬──────┘
                           │
                    ┌──────▼──────┐
                    │  PHP-FPM    │
                    │  Laravel    │
                    └──────┬──────┘
           ┌───────────────┼───────────────┐
           │               │               │
    ┌──────▼──────┐ ┌──────▼──────┐ ┌──────▼──────┐
    │   MySQL     │ │   Redis     │ │  Reverb     │
    │  (primary)  │ │ cache/queue │ │ (real-time) │
    └─────────────┘ └──────┬──────┘ └─────────────┘
                           │
                    ┌──────▼──────┐
                    │   Workers   │
                    │ (Supervisor)│
                    └─────────────┘
```

## Scale-out path

When load increases, separate:

1. Web tier (stateless PHP-FPM instances behind load balancer)
2. Worker tier (queue consumers)
3. Real-time tier (Reverb cluster)
4. Database tier (primary + read replicas as needed)
5. Shared object storage and CDN for widget assets

## Widget CDN

- Serve `widget.js` and static assets from versioned CDN path
- Support emergency disable/rollback without redeploying tenant dashboards
- No secrets in CDN-hosted JavaScript

## Backup and disaster recovery

| Requirement | Detail |
|-------------|--------|
| Database backups | Automated, encrypted, retained per policy |
| Object storage backups | Versioning or periodic sync |
| Restore drills | Tested periodically |
| RPO / RTO | Document targets per business agreement |
| Secrets recovery | Documented procedure independent of application DB |

## Security checklist (production)

- [ ] HTTPS enforced; HSTS where appropriate
- [ ] Firewall: only 80/443 public; DB and Redis internal only
- [ ] Redis password authentication
- [ ] Database least-privilege user (not root)
- [ ] Rate limiting on auth and widget endpoints
- [ ] `.env` outside web root; never committed
- [ ] Regular `composer audit` and dependency updates
- [ ] Malware scanning on uploads (when feature exists)

## Deployment restriction

**Do not deploy** to VPS or production without explicit project owner instruction. This document defines requirements only.

## PHP version upgrade path

Phase 0B is **blocked** — PHP 8.3+ not available locally. Current stack: PHP 8.0.30 / Laravel 9.52.21.

Before production or Module 1:

1. Install PHP 8.3+ per [PHP_UPGRADE_GUIDE.md](../setup/PHP_UPGRADE_GUIDE.md)
2. Complete Phase 0B (Laravel 12 + Vite clean skeleton)
3. Re-run `composer audit` and full test suite
4. Update this document with verified versions

## Observability

Log and monitor:

- Application errors (Sentry, Flare, or equivalent)
- Queue depth and failed jobs
- AI usage and cost per tenant
- Widget availability and latency
- Security events (failed auth, rate limit hits, suspension actions)

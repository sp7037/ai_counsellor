# VPS Production Requirements

**Last updated:** 2026-06-15 (Phase 0)

## Primary production target

AI Counsellor is designed for **managed VPS or cloud VM/container** deployment. This is the reference production environment per [AI_COUNSELLOR_MASTER_ARCHITECTURE.docx](../architecture/AI_COUNSELLOR_MASTER_ARCHITECTURE.docx) §15.

## Recommended stack

| Service | Requirement |
|---------|-------------|
| Web server | Nginx (preferred) or Apache with `public/` document root |
| PHP | 8.2+ recommended (8.1 minimum for Laravel 10+ after upgrade) |
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

Current local development uses PHP 8.0 / Laravel 9. Before production launch:

1. Upgrade to PHP 8.2+
2. Upgrade to Laravel 10 or 11
3. Re-run full test suite and security audit
4. Update [TECHNOLOGY_DECISIONS.md](../architecture/TECHNOLOGY_DECISIONS.md)

## Observability

Log and monitor:

- Application errors (Sentry, Flare, or equivalent)
- Queue depth and failed jobs
- AI usage and cost per tenant
- Widget availability and latency
- Security events (failed auth, rate limit hits, suspension actions)

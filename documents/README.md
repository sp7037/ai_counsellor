# AI Counsellor SaaS — Project Documentation

This directory contains permanent project documentation for the AI Counsellor multi-tenant SaaS platform.

## Principal architecture source

The master architecture document is the authoritative reference for product objectives, tenancy design, database blueprint, security, billing, deployment, and module sequence:

```
documents/architecture/AI_COUNSELLOR_MASTER_ARCHITECTURE.docx
```

All development agents must read it before making architectural or feature changes.

## Documentation index

| Document | Purpose |
|----------|---------|
| [IMPLEMENTATION_STATUS.md](architecture/IMPLEMENTATION_STATUS.md) | Current phase status, completed work, and Module 1 implementation plan |
| [TECHNOLOGY_DECISIONS.md](architecture/TECHNOLOGY_DECISIONS.md) | Selected stack versions and rationale |
| [DATABASE_CONVENTIONS.md](architecture/DATABASE_CONVENTIONS.md) | Primary keys, tenant columns, money, time, status, audit rules |
| [SECURITY_BASELINE.md](architecture/SECURITY_BASELINE.md) | Non-negotiable security requirements |
| [MODULE_ROADMAP.md](architecture/MODULE_ROADMAP.md) | Module sequence and status |
| [AGENT_INSTRUCTIONS.md](agents/AGENT_INSTRUCTIONS.md) | Mandatory rules for all development agents |
| [CHANGE_REPORT_TEMPLATE.md](agents/CHANGE_REPORT_TEMPLATE.md) | Completion report format |
| [AUTHENTICATION_DECISION.md](architecture/AUTHENTICATION_DECISION.md) | Module 1 auth approach (Breeze Blade; not implemented) |
| [PHP_UPGRADE_GUIDE.md](setup/PHP_UPGRADE_GUIDE.md) | Owner guide to unblock Phase 0B (PHP 8.3+ required) |
| [LOCAL_SETUP.md](setup/LOCAL_SETUP.md) | XAMPP local development setup |
| [CPANEL_LIMITATIONS.md](setup/CPANEL_LIMITATIONS.md) | Restricted cPanel deployment notes |
| [VPS_PRODUCTION_REQUIREMENTS.md](setup/VPS_PRODUCTION_REQUIREMENTS.md) | Primary production environment requirements |

## Product scope

AI Counsellor is a centrally hosted, subscription-based, multi-tenant SaaS platform. Organisations embed a configurable AI counselling widget on their websites. The platform supports education consultants, colleges, universities, training organisations, healthcare administrative intake, telemedicine scheduling, and other service-based organisations — without hard-coding a single industry or organisation name.

## Development phases

Work proceeds module-by-module as defined in the master architecture and [MODULE_ROADMAP.md](architecture/MODULE_ROADMAP.md). Do not skip ahead or implement unrelated features.

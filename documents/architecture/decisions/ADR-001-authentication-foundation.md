# ADR-001: Authentication foundation for Laravel 13 (Module 1)

**Status:** Accepted  
**Date:** 2026-06-15  
**Context:** Module 1 requires Blade + Livewire authentication compatible with Laravel 13, without React/Vue/Inertia or obsolete `breeze:install livewire`.

## Decision

Implement an **equivalent Laravel 13 Livewire Starter Kit foundation** using:

| Package | Role |
|---------|------|
| `laravel/fortify` | Password reset, email verification, profile/password update actions |
| `livewire/livewire` v4 | Full-page and interactive UI |
| `livewire/volt` | Single-file auth and dashboard components |
| `livewire/flux` | Official UI components (Tailwind 4) |

Auth **views and routes** follow the official [laravel/livewire-starter-kit](https://github.com/laravel/livewire-starter-kit) Volt patterns. Fortify `views` are disabled (`config/fortify.php`) to avoid duplicate routes; Fortify actions back password reset.

## Why not install `laravel/livewire-starter-kit` directly?

- Packagist package `laravel/livewire-starter-kit` v1.0.x requires `laravel/framework ^12.0` and conflicts with this project's Laravel 13.
- `laravel new --livewire` is the supported greenfield path; this application was scaffolded in Phase 0B without a starter kit.
- `breeze:install livewire` is explicitly excluded by project architecture guidance for Laravel 13.

## Registration policy (B2B managed SaaS)

- **Public self-registration is disabled** (`Features::registration()` removed from Fortify).
- No `/register` route is published.
- Tenant owners are created by platform super-admins during tenant provisioning.
- Users authenticate via `/login` only.

## Consequences

- UI matches official Livewire starter kit conventions (Flux layouts, Volt auth screens).
- Future Laravel 13 starter-kit package releases can be diffed against this implementation when `^13` support ships.
- Two-factor authentication, passkeys, and social login remain deferred.
- Fortify-published `two_factor_*` columns and `passkeys` table migrations are **retained** for starter-kit compatibility but features remain disabled in `config/fortify.php` with no registered routes.

## References

- [Laravel 13 Fortify](https://laravel.com/docs/13.x/fortify)
- [Laravel 13 Starter Kits](https://laravel.com/docs/13.x/starter-kits)
- [AUTHENTICATION_DECISION.md](../AUTHENTICATION_DECISION.md)

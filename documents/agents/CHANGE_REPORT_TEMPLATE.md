# Change Report Template

Copy this template for every module or phase completion report.

---

## Module

**Module name:**  
**Phase:**  
**Status:** Complete / Partial / Blocked  
**Date:**  
**Agent:**

## Scope completed

<!-- What was implemented in this assignment -->

## Out of scope / deferred

<!-- What was intentionally not done -->

## Changed files

<!-- Full relative paths, one per line -->

```
```

## New migrations and rollback

| Migration | Tables / columns | Rollback notes |
|-----------|------------------|----------------|
| | | |

## New environment variables

| Variable | Purpose | Required |
|----------|---------|----------|
| | | |

## Automated tests

| Test file | Result (pass/fail) | Notes |
|-----------|-------------------|-------|
| | | |

**Command run:**

```bash
php artisan test
```

## Manual test steps and results

1. 
2. 
3. 

## Security / tenant-isolation checks

- [ ] No secrets committed
- [ ] `.env` ignored by Git
- [ ] CSRF enabled on web routes
- [ ] Authorisation policies applied (if applicable)
- [ ] Tenant isolation verified (if applicable)
- [ ] Rate limiting applied (if applicable)

## Known limitations

<!-- Honest list of gaps, technical debt, or blocked items -->

## Risks or blockers

<!-- Unresolved issues requiring human decision -->

## Deployment notes

<!-- Local-only unless explicitly instructed to deploy -->

## Architecture / ADR updates

<!-- List ADRs created or architecture docs updated -->

## Recommended next step

<!-- Concise description of the next development phase -->

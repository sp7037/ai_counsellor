# PHP Upgrade Guide — Owner Action Required

**Status:** Phase 0B is **blocked** until PHP 8.3 or newer is available on this machine.  
**Last updated:** 2026-06-15

## Why this is required

Phase 0 installed Laravel 9 on PHP 8.0.30. That stack is unsuitable for new commercial SaaS development:

| Issue | Impact |
|-------|--------|
| PHP 8.0 end-of-life | No security patches |
| Laravel 9 end-of-life | No security patches |
| `composer audit` | 13 advisories on 7 packages (including high-severity Symfony issues) |
| Laravel Mix 6 + Node 22 | `npm run production` fails; no Vite |
| Missing `intl` extension | Required for Laravel 10+ and localisation |

No business feature code exists yet. Upgrading PHP **before** Module 1 is the safest path.

---

## What was audited (2026-06-15)

| Location checked | Result |
|------------------|--------|
| `where.exe php` | `D:\xampp\php\php.exe` only |
| `D:\xampp\php` | PHP **8.0.30** |
| `D:\php`, `D:\php83`, `D:\php84`, `C:\php` | Not found |
| Laragon, WAMP, standalone installs | Not found |

**Conclusion:** No PHP 8.3+ executable exists. Phase 0B modernisation was **not performed**.

---

## Recommended target after upgrade

| Component | Target |
|-----------|--------|
| PHP | **8.3.x** or **8.4.x** (64-bit, Thread Safe for Apache module, NTS for CLI-only workflows) |
| Laravel | **Latest stable Laravel 12.x** (verify at upgrade time; requires PHP 8.2+) |
| Frontend | **Vite** (bundled with modern Laravel skeleton) |
| Node.js | **22.x** (current install is compatible with Vite; no downgrade needed) |
| Database | MariaDB 10.4 locally (MySQL-compatible schema only) |

Do not use beta, RC, or nightly PHP or Laravel releases.

---

## Option A — Upgrade XAMPP (recommended for this project)

### Recommended XAMPP version

Install the latest **XAMPP for Windows** that bundles **PHP 8.3** or **PHP 8.4** and **MariaDB 10.4+**.

Download from: https://www.apachefriends.org/

Before installing, confirm the package lists PHP 8.3+ in its release notes.

### Preserve existing projects and databases

1. **Back up databases** before any XAMPP change:
   ```powershell
   D:\xampp\mysql\bin\mysqldump.exe -u root --all-databases > D:\xampp_backup_all_databases.sql
   ```
   If MySQL is not running, start it from XAMPP Control Panel first.

2. **Back up `htdocs`** (optional but recommended):
   ```powershell
   robocopy D:\xampp\htdocs D:\xampp_htdocs_backup /E
   ```

3. **Back up current XAMPP `php` folder** (if you customised `php.ini`):
   ```powershell
   robocopy D:\xampp\php D:\xampp_php80_backup /E
   ```

4. **Install new XAMPP** to the same path (`D:\xampp`) only if you accept overwriting the old installation, **or** install to a new path (e.g. `D:\xampp84`) and update Apache/document roots manually.

   **Safer approach:** Install to `D:\xampp84`, keep old `D:\xampp` until verified, then switch Apache service or update shortcuts.

5. **Restore databases** if the new install has an empty data directory:
   ```powershell
   D:\xampp\mysql\bin\mysql.exe -u root < D:\xampp_backup_all_databases.sql
   ```

6. **Verify PHP version:**
   ```powershell
   D:\xampp\php\php.exe -v
   ```
   Must show **8.3.x** or **8.4.x**.

### Required PHP extensions

Enable in `php.ini` (uncomment or add):

```ini
extension=curl
extension=fileinfo
extension=gd
extension=intl
extension=mbstring
extension=openssl
extension=pdo_mysql
extension=zip
extension=bcmath
```

Current PHP 8.0.30 is **missing `intl`** — enable it on the new install.

Verify:

```powershell
php -m
```

---

## Option B — Standalone PHP (keep existing XAMPP Apache/MySQL)

If you prefer not to replace XAMPP:

1. Download **PHP 8.3+ VC x64 Thread Safe** from https://windows.php.net/download/
2. Extract to `D:\php83` (or similar).
3. Copy `php.ini-development` to `php.ini`; enable extensions listed above.
4. Use the **absolute path** for all Composer and Artisan commands:
   ```powershell
   D:\php83\php.exe D:\xampp\php\composer install
   D:\php83\php.exe artisan migrate
   ```
5. Optionally configure Apache `httpd-xampp.conf` to point `PHPIniDir` and `LoadModule php_module` to the new PHP folder.

   **This modifies Apache configuration — do only with explicit intent.**

Do **not** modify Windows registry or system PATH unless you understand the impact on other projects.

---

## After PHP 8.3+ is confirmed

Re-run Phase 0B (or provide this prompt to the development agent):

1. Preserve `documents/` and Git history (baseline commit `776a25b` exists).
2. Backup at `D:\xampp\htdocs\ai_counsellor_phase0_backup` already created.
3. Create clean Laravel 12 skeleton in a temp directory.
4. Replace Laravel 9 skeleton; restore `documents/` and `.env` values.
5. Configure Vite, run `npm run build`, `composer audit`, migrations.
6. Mark Phase 0B complete before Module 1.

### Composer command (use selected PHP)

```powershell
cd D:\xampp\htdocs\ai_counsellor
D:\php83\php.exe D:\xampp\php\composer create-project laravel/laravel:^12.0 ai_counsellor_temp --prefer-dist
```

Adjust `D:\php83\php.exe` to your actual PHP 8.3+ path.

---

## What not to do

- Do not start Module 1 on PHP 8.0 / Laravel 9.
- Do not force-install Laravel 12 on PHP 8.0.
- Do not overwrite XAMPP without backing up databases.
- Do not commit `.env` or production credentials.

---

## Verification checklist (owner)

After PHP upgrade, confirm:

- [ ] `php -v` shows 8.3.0 or higher
- [ ] `php -m` includes `intl`, `pdo_mysql`, `mbstring`, `openssl`, `zip`, `bcmath`
- [ ] MariaDB/MySQL starts in XAMPP Control Panel
- [ ] Existing `htdocs` projects still accessible (if preserved)
- [ ] Notify development agent to resume Phase 0B

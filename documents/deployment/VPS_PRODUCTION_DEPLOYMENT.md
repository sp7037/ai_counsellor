# VPS / Cloud Production Deployment Guide

**Last updated:** 2026-06-15  
**Application:** AI Counsellor SaaS (Laravel 13, PHP 8.3+)  
**Target:** Ubuntu LTS VPS or cloud VM (primary production reference)

This guide complements [VPS_PRODUCTION_REQUIREMENTS.md](../setup/VPS_PRODUCTION_REQUIREMENTS.md) with operational steps. The same codebase deployed on cPanel staging moves here without application changes — only environment and infrastructure differ.

## Recommended server (beta / early production)

| Resource | Minimum |
|----------|---------|
| RAM | 4 GB |
| CPU | 2 vCPU |
| Disk | 50 GB SSD |
| OS | Ubuntu 22.04 or 24.04 LTS |

Scale web, worker, and database tiers separately as load grows.

## Stack overview

```
Internet → Nginx (443) → PHP 8.3-FPM → Laravel (public/)
                ↓
         MySQL 8+ / MariaDB
                ↓
    Redis (optional: cache + queue)
                ↓
    Supervisor → queue:work
    Cron → schedule:run
```

## Initial server setup

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server redis-server supervisor git unzip \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl \
  php8.3-zip php8.3-bcmath php8.3-intl
```

Install Composer globally: https://getcomposer.org/download/

Install Node **22.12+** for asset builds (or build in CI and deploy artifacts).

## Database

```bash
sudo mysql_secure_installation
```

Create database and least-privilege user:

```sql
CREATE DATABASE ai_counsellor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ai_app'@'localhost' IDENTIFIED BY 'strong-password';
GRANT ALL PRIVILEGES ON ai_counsellor.* TO 'ai_app'@'localhost';
FLUSH PRIVILEGES;
```

## Deploy application

```bash
cd /var/www
sudo git clone https://github.com/sp7037/ai_counsellor.git
cd ai_counsellor
sudo chown -R www-data:www-data /var/www/ai_counsellor
```

```bash
cp .env.example .env
# Edit .env: APP_ENV=production, APP_DEBUG=false, APP_URL=https://yourdomain.com
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan db:seed --class=PlansSeeder
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Set permissions:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

## Nginx virtual host

`/etc/nginx/sites-available/ai-counsellor`:

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com;
    root /var/www/ai_counsellor/public;

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable site and reload Nginx.

## SSL (Certbot)

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d yourdomain.com
```

## Redis (optional, recommended at scale)

`.env`:

```dotenv
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=your-redis-password
```

Bind Redis to localhost and require authentication.

## Supervisor — queue workers

`/etc/supervisor/conf.d/ai-counsellor-worker.conf`:

```ini
[program:ai-counsellor-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/ai_counsellor/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/ai_counsellor/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start ai-counsellor-worker:*
```

## Cron — scheduler

```cron
* * * * * www-data cd /var/www/ai_counsellor && php artisan schedule:run >> /dev/null 2>&1
```

## Production `.env` highlights

```dotenv
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=warning
SESSION_SECURE_COOKIE=true

FAKE_AI_ENABLED=false
FAKE_PAYMENT_ENABLED=false
FAKE_MESSAGING_ENABLED=false

RAZORPAY_ENABLED=true
PAYMENT_ENVIRONMENT=live
META_WHATSAPP_ENABLED=true
MESSAGING_ENVIRONMENT=live
```

## Backups

| Asset | Strategy |
|-------|----------|
| Database | Daily `mysqldump` + encrypted off-site storage |
| `storage/app` | Sync to object storage (S3-compatible) |
| `.env` | Sealed secret manager / offline secure store |
| Code | Git tags; deploy from known commits |

Test restore quarterly.

## Log rotation

Laravel logs: `storage/logs/laravel.log`

Configure `logrotate` for `storage/logs/*.log` (daily, retain 14–30 days).

## Deployment / update procedure

```bash
cd /var/www/ai_counsellor
php artisan down
git pull origin master
composer install --no-dev --optimize-autoloader
npm ci && npm run build   # or deploy pre-built public/build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan up
```

## Health and smoke checks

| Check | Command / URL |
|-------|----------------|
| HTTP health | `curl -fsS https://yourdomain.com/up` |
| Config | `php artisan about` (SSH only) |
| Scheduler | Verify cron logs |
| Queue | `supervisorctl status` |
| Automated tests | Run in CI before deploy — `php artisan test` |

## Widget CDN (future)

Serve `public/build/widget.js` from a versioned CDN path. Emergency disable without redeploying tenant dashboards.

## Security

- Firewall: allow 80/443 only publicly; MySQL and Redis internal
- `composer audit` and `npm audit` in CI
- Rate limits on auth and widget routes (built-in)
- No `.env` in web root

## Portability from cPanel

No code changes required. On VPS:

1. Switch `QUEUE_CONNECTION` to `redis` (optional)
2. Add Supervisor workers
3. Use Nginx + PHP-FPM instead of Apache
4. Enable Redis cache/session if desired
5. Set `PAYMENT_ENVIRONMENT=live` and production provider keys when ready

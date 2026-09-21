# Deploying CoreMemory

Target: a small Ubuntu VPS running Nginx, PHP-FPM, MySQL, Redis and one queue
worker. A 2 GB droplet is comfortable for a studio this size.

Written for someone deploying their first Laravel app. Every command is meant
to be pasted as-is, in order.

---

## 1. Server packages

```bash
sudo apt update && sudo apt upgrade -y

sudo apt install -y nginx mysql-server redis-server supervisor unzip git \
  php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl \
  php8.4-zip php8.4-gd php8.4-intl php8.4-bcmath
```

`php8.4-gd` is not optional — image conversions and the placeholder generator
both need it.

Composer:

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

Node (for building assets — you can also build locally and upload `public/build`):

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

---

## 2. Database

```bash
sudo mysql
```

```sql
CREATE DATABASE corememory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'corememory'@'localhost' IDENTIFIED BY 'a-long-random-password';
GRANT ALL PRIVILEGES ON corememory.* TO 'corememory'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## 3. The application

```bash
sudo mkdir -p /var/www/corememory
sudo chown -R $USER:www-data /var/www/corememory

git clone <your-repo-url> /var/www/corememory
cd /var/www/corememory

composer install --no-dev --optimize-autoloader
npm ci && npm run build

cp .env.example .env
php artisan key:generate
```

Edit `.env` for production:

```ini
APP_ENV=production
APP_DEBUG=false                 # never true in production — it leaks stack traces
APP_URL=https://corememory.my

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=corememory
DB_USERNAME=corememory
DB_PASSWORD=the-password-you-just-set

# Redis in production; database drivers are a local-development convenience.
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

MAIL_MAILER=smtp
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="hello@corememory.my"
STUDIO_NOTIFICATION_EMAIL="studio@corememory.my"
```

Then:

```bash
php artisan migrate --force        # --force is required in production
php artisan storage:link
php artisan db:seed --class=RoleSeeder --force   # creates the admin roles
```

**Do not run the full seeder in production** — it would create demo bookings,
invoices and placeholder content. Only `RoleSeeder` is safe.

Create your real admin user:

```bash
php artisan tinker
```

```php
$user = App\Models\User::create([
    'name' => 'Studio Owner',
    'email' => 'you@corememory.my',
    'password' => Hash::make('a-strong-password'),
    'email_verified_at' => now(),
]);
$user->assignRole('admin');
```

Permissions:

```bash
sudo chown -R www-data:www-data /var/www/corememory/storage \
                                /var/www/corememory/bootstrap/cache
sudo chmod -R 775 /var/www/corememory/storage \
                  /var/www/corememory/bootstrap/cache
```

---

## 4. Nginx

`/etc/nginx/sites-available/corememory`:

```nginx
server {
    listen 80;
    server_name corememory.my www.corememory.my;

    # Certbot rewrites this block for HTTPS in step 5.
    root /var/www/corememory/public;
    index index.php;

    charset utf-8;

    # Wedding photographs are large. Without this, an upload of a few full-size
    # RAW-derived JPEGs fails with a confusing 413.
    client_max_body_size 32M;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    # Vite output is content-hashed, so it can be cached hard and forever.
    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Uploaded media — long cache, but not immutable: replacing a photograph
    # keeps the same path.
    location /storage/ {
        expires 30d;
        add_header Cache-Control "public";
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;

        # PDF generation and image conversions can exceed the 30s default.
        fastcgi_read_timeout 120;
    }

    # Never serve dotfiles — .env included.
    location ~ /\.(?!well-known).* {
        deny all;
    }

    error_log  /var/log/nginx/corememory-error.log;
    access_log /var/log/nginx/corememory-access.log;
}
```

Enable it:

```bash
sudo ln -s /etc/nginx/sites-available/corememory /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

---

## 5. HTTPS

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d corememory.my -d www.corememory.my
```

Certbot renews automatically. The app forces HTTPS in production
(`AppServiceProvider`), so signed invoice links stay valid behind the proxy.

---

## 6. The queue worker

**This is not optional.** Booking confirmation emails, studio notifications,
invoice emails and image conversions all run on the queue. Without a worker,
a couple submits a booking and never receives anything.

`/etc/supervisor/conf.d/corememory-worker.conf`:

```ini
[program:corememory-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/corememory/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
directory=/var/www/corememory
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/corememory/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start corememory-worker:*
sudo supervisorctl status
```

`--max-time=3600` restarts each worker hourly. Long-running PHP processes hold
stale code in memory; restarting means a deploy is picked up without anyone
remembering to restart workers by hand.

---

## 7. The scheduler

One cron entry runs everything. Currently that is `bookings:expire-stale`,
which cancels pending enquiries nobody answered.

```bash
sudo crontab -u www-data -e
```

```cron
* * * * * cd /var/www/corememory && php artisan schedule:run >> /dev/null 2>&1
```

Laravel decides what is actually due; the cron just wakes it every minute.

---

## 8. Deploying an update

```bash
cd /var/www/corememory

php artisan down --render="errors::503"

git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan migrate --force

# Rebuild caches. optimize:clear FIRST — caching on top of a stale cache is
# how a deploy appears to succeed and then serves yesterday's routes.
php artisan optimize:clear
php artisan optimize          # config + routes + views + events

# Workers hold old code in memory until told otherwise.
sudo supervisorctl restart corememory-worker:*

php artisan up
```

A one-liner for later:

```bash
#!/usr/bin/env bash
set -euo pipefail
cd /var/www/corememory
php artisan down --render="errors::503" || true
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize:clear && php artisan optimize
sudo supervisorctl restart corememory-worker:*
php artisan up
```

---

## 9. Backups

The database and `storage/app/public` (client photographs) are the only
irreplaceable things on this server. Everything else can be rebuilt from git.

```bash
sudo crontab -e
```

```cron
# 02:00 daily — database
0 2 * * * mysqldump -u corememory -p'password' corememory | gzip > /var/backups/corememory-$(date +\%F).sql.gz

# 03:00 Sundays — uploaded media
0 3 * * 0 tar -czf /var/backups/corememory-media-$(date +\%F).tar.gz -C /var/www/corememory/storage/app public

# Keep 30 days
0 4 * * * find /var/backups -name 'corememory-*' -mtime +30 -delete
```

Copy these off the server — a backup on the same disk is not a backup. Verify
a restore at least once, before you need it.

---

## 10. Checks after deploying

```bash
curl -I https://corememory.my                    # 200
curl -s https://corememory.my/sitemap.xml | head # XML
sudo supervisorctl status                        # workers RUNNING
php artisan queue:monitor redis:default          # queue not backing up
tail -f storage/logs/laravel.log                 # quiet
```

Then, by hand:

- Submit a booking on `/book`, confirm the email arrives.
- Generate an invoice in the admin and download the PDF.
- Open a signed client invoice link in a private window (no login needed).

---

## Troubleshooting

**500 with a blank page** — `storage/logs/laravel.log`. Almost always file
permissions on `storage/`.

**Emails never arrive** — `sudo supervisorctl status`. If workers are running,
check `failed_jobs`:

```bash
php artisan queue:failed
php artisan queue:retry all
```

**Images upload but never appear** — conversions are queued; check the worker.
`php artisan storage:link` must also have been run.

**Changes not showing after a deploy** — a stale cache.
`php artisan optimize:clear && php artisan optimize`.

**Invoice PDFs fail** — check `php8.4-gd` is installed and `storage/app/private`
is writable by `www-data`.

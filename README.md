# CoreMemory

Wedding photography and videography booking and portfolio platform for a
Malaysian studio.

Prices and packages are published openly so couples self-select. By the time an
enquiry reaches the studio, the date, session slot, package and add-ons are
already chosen — the team receives a structured quotation request instead of a
cold "hi, berapa harga?".

- **Public site** — portfolio, packages with real prices, availability checker,
  booking wizard
- **Admin panel** — Filament, built for a non-technical studio owner
- **Invoicing** — deposit and final invoices as PDFs, with payment tracking
- **Dashboard** — revenue, costs, gross profit and the enquiry→booking funnel

> **Status:** all five phases complete. See [CLAUDE.md](CLAUDE.md) for the
> developer guide and [DEPLOYMENT.md](DEPLOYMENT.md) for the VPS setup.

---

## Requirements

| | |
| --- | --- |
| PHP | 8.4+ (8.2 minimum) |
| Composer | 2.x |
| Node | 20+ |
| Database | MySQL 8 or MariaDB 10.4+ |

**macOS with no PHP installed?** This one command installs PHP 8.4 and Composer
with no GUI and no `sudo`:

```bash
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.4)"
```

Then restart your terminal. If you have XAMPP, note its bundled PHP is **7.4 and
will not run this project** — the command above installs a separate, newer PHP
and puts it first on your PATH.

---

## Setup

```bash
# 1. Get the code and its dependencies
git clone <repo-url> corememory
cd corememory
composer install
npm install

# 2. Create your environment file and app key
cp .env.example .env
php artisan key:generate

# 3. Start the database, then create the schema and demo data
./scripts/db start
php artisan migrate:fresh --seed

# 4. Link storage so uploaded images are publicly reachable
php artisan storage:link

# 5. Build the frontend
npm run build
```

Then run the app — **two terminals**:

```bash
npm run dev          # terminal 1 — rebuilds CSS/JS as you edit
php artisan serve    # terminal 2 — serves the app
```

Open <http://localhost:8000>.

### Database setup

`./scripts/db start` runs a MariaDB instance that belongs to this project, on
port **3307**, under your own user account — no `sudo`, and it does not disturb
XAMPP's MySQL on 3306.

```bash
./scripts/db start     # start
./scripts/db stop      # stop
./scripts/db status    # check
./scripts/db shell     # open a SQL prompt
./scripts/db reset     # drop and recreate both databases (destructive)
```

**Prefer XAMPP?** Start MySQL from its control panel and set `DB_PORT=3306` plus
your XAMPP credentials in `.env`. Nothing else changes.

### Admin panel

<http://localhost:8000/admin>

| Email | Role | Password |
| --- | --- | --- |
| `super@corememory.test` | `super_admin` | `password` |
| `owner@corememory.test` | `admin` | `password` |
| `staff@corememory.test` | `staff` | `password` |

Seeded demo accounts, local only. Never use these in production.

### Emails

`MAIL_MAILER=log` locally, so nothing is actually sent. Emails are written to
`storage/logs/laravel.log` where you can read them.

---

## Everyday commands

```bash
php artisan test              # run the test suite
./vendor/bin/pint             # format PHP (run before committing)
php artisan migrate:fresh --seed   # rebuild the database from scratch
php artisan queue:work        # process queued emails and PDF generation
php artisan tinker            # interactive REPL
```

---

## Troubleshooting

**`php -v` shows 7.4** — XAMPP is ahead on your PATH:

```bash
export PATH="$HOME/.config/herd-lite/bin:$PATH"
```

**`SQLSTATE[HY000] [2002] Connection refused`** — the database isn't running:

```bash
./scripts/db start
```

**Page loads but has no styling** — assets aren't built. Run `npm run dev` (or
`npm run build`).

**"Error during upload" on an image, with no reason given** — the file is
larger than PHP's `upload_max_filesize`. PHP rejects it before Laravel boots,
so Filament's own 12 MB limit never gets a chance to explain itself. The
compiled defaults (2M upload, 8M post) are far too small for wedding
photographs. Check and fix:

```bash
php -r 'echo ini_get("upload_max_filesize"), " / ", ini_get("post_max_size"), "\n";'
php --ini          # shows which php.ini is loaded
```

Set `upload_max_filesize = 24M`, `post_max_size = 32M` and `memory_limit = 512M`
in that file, then **restart `php artisan serve`** — a running server keeps the
old values. `UploadLimitsTest` fails if these drift below what the admin form
advertises.

**Image uploads, saves, then the public page still shows a grey placeholder** —
check the upload actually persisted. A failed upload leaves an error on the
form that blocks saving even after you replace the file with a smaller one;
clear the failed file with its × first, then Save.

**Page is blank with JS disabled or the bundle fails** — by design the page
falls back to plain readable HTML after ~2.5s. If it stays blank, check the
browser console for a JS error.

---

## What's in it

| | |
| --- | --- |
| `/` | Homepage — hero, portfolio, packages with prices, stats, testimonials |
| `/work`, `/work/{slug}` | Portfolio, filterable by category |
| `/packages` | Every price and inclusion, published openly |
| `/availability` | Standalone date checker |
| `/book` | The booking wizard — date first, live clash detection |
| `/about`, `/journal`, `/contact` | Studio, writing, and a contact form |
| `/admin` | Filament: bookings, calendar, packages, invoices, content, settings |
| `/admin/finance-dashboard` | Revenue, costs, profit, receivables, funnel |

## Testing

```bash
php artisan test                        # everything
php artisan test --testsuite=Concurrency  # the double-booking guarantees
./vendor/bin/pint                       # format
```

Tests run against the `corememory_testing` MySQL database, not SQLite — the
availability guarantee is a real unique index and MySQL error 1062. See
[CLAUDE.md](CLAUDE.md) § Testing.

## Documentation

**[CLAUDE.md](CLAUDE.md)** — the developer guide: stack decisions and their
reasoning, folder and naming conventions, the theming system, motion rules, how
money is handled, **how the availability engine prevents double bookings**,
invoicing, the finance dashboard, and how to add a package, project or invoice.

**[DEPLOYMENT.md](DEPLOYMENT.md)** — Ubuntu VPS setup: Nginx, PHP-FPM, MySQL,
Redis, the Supervisor queue worker, the scheduler cron, deploys and backups.

Read the Availability section before changing anything booking-related.

# CoreMemory — Developer Guide

Wedding photography and videography studio site for the Malaysian market.

**The point of this project:** enquiries used to arrive as Instagram DMs asking
"berapa harga?". This site publishes prices openly so couples self-select, and
by the time a message reaches the studio the date, package and scope are already
chosen. Fewer conversations, better conversations.

If you only read one section, read **Availability** — it is the part of this
codebase where a mistake costs the studio a double-booked Saturday.

---

## Running it locally

You need PHP 8.4+, Composer, Node 20+, and MariaDB/MySQL.

```bash
git clone <repo> corememory && cd corememory
composer install
npm install

cp .env.example .env
php artisan key:generate

./scripts/db start                    # starts the local MariaDB (see below)
php artisan migrate:fresh --seed
php artisan storage:link

npm run dev                           # terminal 1 — Vite with hot reload
php artisan serve                     # terminal 2 — http://localhost:8000
```

Admin panel: <http://localhost:8000/admin>

| Seeded login | Role | Password |
| --- | --- | --- |
| `super@corememory.test` | `super_admin` | `password` |
| `owner@corememory.test` | `admin` | `password` |
| `staff@corememory.test` | `staff` | `password` |

Emails don't send locally — `MAIL_MAILER=log` writes them to
`storage/logs/laravel.log` so you can read them without emailing a real person.

### PHP on this machine

PHP came from [php.new](https://php.new) ("Herd Lite"), which installs a
standalone PHP 8.4 + Composer into `~/.config/herd-lite/bin` with no GUI and no
sudo. XAMPP's bundled PHP is **7.4 and cannot run this project**.

If `php -v` shows 7.4, your PATH is finding XAMPP first. Fix:

```bash
export PATH="$HOME/.config/herd-lite/bin:$PATH"
```

### Database

Local dev runs a **project-owned MariaDB on port 3307** — not XAMPP's 3306.

Why: XAMPP's MariaDB data directory is owned by `_mysql` and starting it needs
`sudo` every time. This instance runs as you, on its own port, from its own data
directory at `~/.corememory/`. Both can run simultaneously and neither disturbs
the other.

```bash
./scripts/db start | stop | status | shell | reset
```

Config lives at `~/.corememory/my.cnf`; errors at `~/.corememory/log/error.log`.

**Prefer XAMPP instead?** Start MySQL from the XAMPP control panel and set
`DB_PORT=3306` with the credentials you use there. Nothing else changes.

### Tests

```bash
php artisan test
./vendor/bin/pint          # format before committing
```

Tests run against the **`corememory_testing` MySQL database**, not SQLite. That
is deliberate — see [Testing](#testing).

---

## Stack, and why

| Choice | Reason |
| --- | --- |
| **Laravel 13** | Current stable. The brief said 12, but 13 is the latest and matches the sibling `ceritaconvo-booking-system` project. |
| **Filament 5** | The admin is handed to a non-technical studio owner. Hand-building CRUD, filters, calendars, uploads and dashboard charts in Blade would take weeks and end up worse. |
| **MySQL / MariaDB** | The availability guarantee is a real `UNIQUE` index and MySQL error 1062. Postgres or SQLite would need different code. |
| **Blade + Alpine** | No React, no Vue. Alpine covers the small UI state we actually have. |
| **Livewire** | Only the booking wizard and Filament. Do not reach for it elsewhere. |
| **Tailwind v4** | Ships with Laravel 13 and Filament 5. CSS-first — see [Theming](#theming). |
| **dompdf** | Invoices are a bordered table with a letterhead — no flexbox needed. Pure PHP, so the VPS needs no headless Chromium (~400 MB and a class of queue-worker failures Browsershot would add). |

### Approved packages

`spatie/laravel-medialibrary`, `spatie/laravel-permission`,
`spatie/laravel-sluggable`, `spatie/laravel-sitemap`, `barryvdh/laravel-dompdf`,
`pestphp/pest`.

Added in Phase 2: `filament/spatie-laravel-media-library-plugin` — the official
Filament adapter for medialibrary, version-locked to `filament/support`
(`self.version`), so it is effectively part of Filament rather than a new
dependency. Without it every resource needs hand-rolled upload-to-media sync.

**Adding anything else requires justifying it to the project owner first.**
(`laravel/boost` was deliberately not installed for this reason.)

---

## Conventions

Non-negotiable, applied from the first file:

- `declare(strict_types=1);` at the top of **every** PHP file.
- Typed properties, typed parameters, typed returns. No exceptions.
- **Business logic goes in Action classes** — `app/Actions/Bookings/CreateBooking.php`,
  `app/Actions/Invoices/GenerateInvoice.php`. Not in controllers. Not in models.
- **All validation in Form Requests.** Never validate inside a controller.
- **Enums, not loose strings** — `BookingStatus`, `SessionSlot`, `InvoiceStatus`.
- **Money is always integer cents.** Never a float. See [Money](#money).
- **Every user-facing string goes through `__()`** and lives in `lang/en/`.
  A Bahasa Malaysia locale must be addable by creating `lang/ms/` — with zero
  Blade changes.
- **No hardcoded colours, fonts, sizes or radii** outside `resources/css/tokens.css`.
- Every model gets a factory and a seeder.
- Comments explain **why**, not what. If a Laravel beginner would have to guess
  at the reasoning, leave a line or two.

### Folder layout

```
app/
  Actions/          business logic, one class one job
    Bookings/
    Invoices/
  Casts/            Money — integer cents <-> value object
  Enums/            BookingStatus, SessionSlot, ...
  Filament/         admin panel: Resources, Pages, Widgets
  Http/Requests/    all validation
  Models/
  Services/         AvailabilityService and other read-side services
  Support/          small helpers
  ValueObjects/     Money
lang/en/            every UI string
resources/
  css/tokens.css    THE design system. Single source of truth.
  css/app.css       base, component and utility layers
  js/motion.js      ALL scroll-linked animation
  views/
    components/     the Blade component library
      layouts/app.blade.php
    pages/          one file per public page
routes/
  public.php        the site a couple sees
  admin.php         admin routes outside Filament
scripts/db          local database control
```

### Naming

| Thing | Convention | Example |
| --- | --- | --- |
| Models | singular StudlyCase | `BookingDate` |
| Tables | plural snake_case | `booking_dates` |
| Pivots | singular, alphabetical | `booking_add_on` |
| Money columns | always `*_cents` | `subtotal_cents` |
| Booleans | `is_` / `has_` | `is_featured` |
| Actions | imperative verb | `ConfirmBooking` |
| Blade components | kebab-case | `<x-package-card>` |
| Lang keys | `file.section.key` | `booking.availability.blocked` |

---

## Theming

**`resources/css/tokens.css` is the only place colour, type, spacing or radius
is defined.** Restyling the whole site means editing that one file.

There is no `tailwind.config.js` — Tailwind v4 removed it. Everything lives in
the `@theme` block, which emits both CSS custom properties and matching utility
classes. `--color-paper` gives you `bg-paper`, `text-paper`, `border-paper`, and
`var(--color-paper)` for anything hand-rolled.

**Never write a hex value in a Blade file or a component.** If you need a colour
that doesn't exist, add a token.

Key token groups: `--color-*` (including a full `inverse-*` set for the dark
footer), `--font-*`, `--text-*` (all fluid via `clamp()`), `--spacing-*`,
`--radius-*` (near-zero — sharp corners are the design), and `--duration-*` /
`--ease-*` / `--reveal-*`, which `motion.js` reads so JS and CSS can't drift.

---

## Motion

**All scroll-linked animation lives in `resources/js/motion.js`.** One module,
so the three non-negotiables are enforceable in one place:

1. **`prefers-reduced-motion: reduce` kills everything.** No Lenis, no
   ScrollTrigger, every element rendered in its final state.
2. **Under 768px**, reveals stay; heavy parallax and the custom cursor are dropped.
3. **Every ScrollTrigger is killed on navigation** — `pagehide` and Livewire
   navigation events both tear down.

Rules for anything added there:

- Animate `transform` and `opacity` **only**. Never width, height, top or margin.
- The custom cursor requires `(hover: hover) and (pointer: fine)`. Never on touch.
- If an effect can't hold 60fps, drop it.

### The reveal failsafe

Reveal targets start at `opacity: 0`, which is only safe while JS is running. An
inline script in `<head>` sets `data-motion="on"` before first paint (no flash of
the final state) **and arms a 2.5s timer that strips the attribute** if `app.js`
never loads. So a broken bundle degrades to a plain readable page, not a blank
one. `app.js` disarms it via `window.__coreMemoryMotionReady()`.

Markup hooks: `data-reveal`, `data-reveal-group`, `data-parallax`, `data-hero`,
`data-marquee`, `data-count-to`, `data-sticky-feature`, `data-cursor`.

---

## Money

Money is **never** a float. `0.1 + 0.2 !== 0.3` in binary floating point, and on
an invoice that becomes a one-sen discrepancy the studio has to explain.

- Every money column is an integer named `*_cents`.
- `App\ValueObjects\Money` wraps it and does the arithmetic.
- `App\Casts\Money` maps the column to the value object.
- Format **only at display time**: `$package->price_cents->formatCompact()` → `RM 3,800`.

```php
protected function casts(): array
{
    return ['price_cents' => MoneyCast::class];
}
```

`percent()` rounds half-up, so a deposit and its balance always add back to
exactly the total.

---

## Caching

**Never put an Eloquent model or collection into the cache.**

This is not a style preference. Serialising models and reading them back throws
`The script tried to call a method on an incomplete object` at *render* time —
a 500 in production, from code that looks completely fine in review. It bit this
project once already; `PortfolioTest` has a regression test that fails if
anything but a primitive crosses the cache boundary.

The pattern to follow — see `HomeController`:

1. Cache the **selection**: record ids, ordering, and any computed numbers.
2. Hydrate the models fresh with a `whereIn` lookup, eager-loading relations.

Ids are ints, so they serialise safely, and the expensive part (the filtering
and ordering logic) is still cached. Hydration is an indexed primary-key
lookup.

`App\Support\Settings` additionally memoises per request, so reading twenty
settings on one page is one cache round trip, not twenty.

**Busting:** `App\Observers\FlushesPublicCache` is registered on Project,
Testimonial, Post and Setting in `AppServiceProvider`. An owner who saves in the
admin must see the change on the site immediately — not after the TTL. If you
add a model whose content appears on a cached page, register it there too.

### N+1

`Model::preventLazyLoading()` is on in local development, so a missing
`with()` throws instead of quietly costing a query per row. Current cost:

| Page | Data queries |
| --- | --- |
| Home (warm cache) | 5 |
| `/work` | 5 |
| `/work/{slug}` | 8 |

Locally, `CACHE_STORE` and `SESSION_DRIVER` are `database`, so query logs also
show cache and session traffic. Those become Redis on the VPS.

---

## Availability

**Read this before touching anything booking-related.**

Availability is tracked per **date + session slot**. Slots are Morning,
Afternoon, Evening and Full Day. One crew, so a slot is exclusive.

### Slot expansion — the core idea

**`full_day` is never stored as a row.** It expands into the three concrete
slots. A Full Day booking writes three rows; a single-slot booking writes one.
Admin blocks write into the same table.

```
slot_holds   event_date, session_slot(morning|afternoon|evening),
             holdable_type, holdable_id, reason
             UNIQUE (event_date, session_slot)
```

Every conflict rule then falls out of **one plain unique index**, with no
application logic to get wrong:

| Scenario | Outcome |
| --- | --- |
| Full Day onto a date with an existing Morning | collides on the morning row |
| Morning onto an existing Full Day | collides on the morning row |
| The same slot twice | collides |
| Morning + Evening on one date | both succeed — correct |
| Admin block vs confirmed booking | collides — one shared namespace |

`SessionSlot::expand()` is the only place expansion happens. `SessionSlot::concrete()`
must never contain `FullDay` — if it does, the index stops protecting anything.

Rows exist **only for blocking statuses**. MySQL has no partial indexes, which
is exactly why holds live in their own table rather than on `bookings`.

### Blocking vs tentative

From `config/booking.php`:

- **Blocking** (`confirmed`, `deposit_paid`) — writes holds, takes the date off
  the calendar, backed by the unique index.
- **Tentative** (`pending`) — writes **no** holds. More than one couple may
  enquire for the same slot; the studio decides between them. The UI says
  "another enquiry is pending for this slot".

Adding `pending` to `blocking_statuses` makes enquiries hold their slot
immediately, with **no code change** — holds are written wherever the status
qualifies.

### Concurrency

`ConfirmBooking` opens a transaction, inserts the hold rows, and catches MySQL
error **1062** → `SlotUnavailableException`. **The unique index is the lock.**
No advisory locking, no `SELECT ... FOR UPDATE` race window.

Two admins confirming different bookings onto the same slot at the same instant:
exactly one succeeds.

### A request is not a confirmation

Say it in the UI and say it in the emails. A submitted booking is `pending`. The
studio confirms manually. The required disclaimer
(`lang/en/booking.php → availability_disclaimer`) appears at the calendar step,
the review step, **and** the confirmation email. Style it as a micro-label note —
unmissable, but not an alarming red box.

### Booking window

`min_lead_days` (14) and `max_ahead_months` (24), both in `config/booking.php`.
Dates outside the window grey out **with a reason**, never silently vanish.

---

## How to...

### Add a package

Admin → Packages → New. Name, price, "from" toggle, duration, inclusions
(repeater), popular badge, active toggle, sort order. No code.

In code: `PackageSeeder` for demo data; `price_cents` is an integer with the
`MoneyCast`. Prices are captured onto a booking at booking time, so changing a
package price never rewrites an existing booking or a past invoice.

### Add a project (wedding story)

Admin → Projects → New. Couple names, category, date, venue, crew, gallery
(drag to reorder), featured toggle. Slug is generated by `spatie/laravel-sluggable`.
Images go through medialibrary with thumbnail/grid/full WebP conversions and the
original preserved.

### Generate an invoice

Admin → Bookings → open one → **Generate deposit invoice**. After the event,
**Generate final invoice** for the balance.

- Numbering `CM-INV-2026-0001`, allocated from a sequence row inside the
  transaction. **Never** from `count() + 1` — that reuses numbers after a delete
  and races under concurrency.
- Line items are a **snapshot** copied at booking prices. Editable while `draft`;
  locked (`locked_at`) once the status leaves draft.
- Regenerating a PDF never changes an issued number or a historical price.
- Sending is always an explicit admin action, never automatic.
- Clients download via a **signed, expiring URL** — no login, not guessable.

### Add a new UI string

Add the key to `lang/en/site.php` or `lang/en/booking.php` and use `__()`.
Never hardcode a string in a Blade template.

### Add a design token

Add it to the `@theme` block in `resources/css/tokens.css`. Never a hex value in
a component.

---

## Testing

Pest. Run with `php artisan test`.

**Tests use MySQL (`corememory_testing`), not SQLite.** This costs some speed and
is worth it: the availability guarantee is a MySQL unique index and error 1062.
SQLite raises a different error and does not reproduce the concurrent-submit race
at all — an in-memory suite would pass happily while the real double-booking bug
shipped.

Most tests use `RefreshDatabase`, which rolls back per test — so **seeded roles
are gone**; call `$this->seed(RoleSeeder::class)` when a test needs them.

**Phase 3 note:** the concurrent double-booking tests must **not** use
`RefreshDatabase`. They need two genuinely separate connections committing
against the same table, so they opt into `DatabaseTruncation` instead.

Required coverage: booking creation · concurrent-confirm double-booking
prevention · full-day vs slot matrix · admin block vs booking · window
enforcement · validation failures · price calculation with quantified add-ons ·
reference sequencing · invoice numbering under concurrency · snapshot
immutability after lock · profit and margin calculation · ageing buckets.

---

## Build phases

- **Phase 0** — environment ✅
- **Phase 1** — foundation: tokens, motion, layout, components, roles ✅
- **Phase 2** — content & portfolio: models, media, Filament resources, full homepage, `/work` ✅
- **Phase 3** — packages, availability engine, booking wizard, emails, tests
- **Phase 4** — invoicing, payments, costs, profit dashboard
- **Phase 5** — about, contact, journal, SEO, performance, accessibility, deploy notes

Placeholder routes in `routes/public.php` are annotated with the phase that
replaces them.

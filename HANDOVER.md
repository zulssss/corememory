# CoreMemory — developer handover

A read-through for a second developer. What was built, which decisions carry
weight, where the traps were, and what is honestly still missing.

This is the map. The detail lives in three other files, linked throughout:

| File | Covers |
| --- | --- |
| [README.md](README.md) | Install, run locally, everyday commands, troubleshooting |
| [CLAUDE.md](CLAUDE.md) | Conventions, theming, motion, money, the availability engine, caching rules, invoicing, the dashboard |
| [DEPLOYMENT.md](DEPLOYMENT.md) | Ubuntu VPS: Nginx, PHP-FPM, MySQL, Redis, Supervisor, cron, deploys, backups |

---

## 1. What this is

CoreMemory is a wedding photography and videography studio in Malaysia. Every
enquiry currently arrives as an Instagram DM, and the team burns hours
re-sending the same price list to people who were never going to book.

The site inverts that funnel. Prices and packages are published openly, the
couple self-selects, and the date, session slot, package and add-ons are all
chosen before a human is involved. What lands with the studio is a structured
quotation request rather than "hi, berapa harga?".

Behind it sits a Filament admin the non-technical owner runs alone: bookings,
availability, packages, content, invoicing, and a profit dashboard.

---

## 2. Status at a glance

**Complete.** All five phases built, committed and pushed. Never deployed.

| | |
| --- | --- |
| Laravel | 13.32.0 |
| PHP | 8.4.1 |
| Admin | Filament 5.8.2 (Livewire 4) |
| Frontend | Blade + Alpine + Tailwind v4, Vite 8 |
| Motion | Lenis + GSAP/ScrollTrigger |
| Database | MariaDB 10.4 local · MySQL 8 target |
| Tests | Pest 4.7 — **245 tests, 597 assertions, ~35s** |

**Scale:** 348 tracked files · 135 PHP files under `app/` · 23 migrations ·
57 Blade views · 8 locale files.

**Commits** — one per phase:

```
0d45d17  Phase 5: about, journal, contact, SEO, performance, accessibility, deploy
f6aa7cc  Format InvoiceTest with Pint
78468c6  Phase 4: invoicing, payments, costs and the profit dashboard
a2b4917  Phase 3: packages, availability engine, booking wizard
846dece  Phase 2: content & portfolio — models, media, admin resources, /work
8772730  Phase 1: foundation — tokens, motion system, layout shell, roles
```

**Test distribution** — weighted toward the parts that cost money if wrong:

| Suite | Tests | |
| --- | ---: | --- |
| `AvailabilityTest` | 28 | The conflict matrix |
| `InvoiceTest` | 27 | Numbering, snapshots, locking, PDFs |
| `BookingWizardTest` | 21 | The public flow |
| `ContentPagesTest` | 19 | Pages, SEO, accessibility |
| `FinanceReportTest` | 19 | Cash vs accrual, profit, ageing |
| `FoundationTest` | 15 | Money, enums, slot expansion |
| `PortfolioTest` | 14 | |
| `AdminBookingTest` | 13 | Status transitions |
| `PerformanceTest` | 11 | N+1, image delivery, motion |
| `AdminPanelTest` | 6 | |
| `BookingCalendarTest` · `BookingNotificationTest` · `ExpireStaleBookingsTest` | 5 each | |
| **`Concurrency/DoubleBookingTest`** | **5** | Two real connections racing |
| **`Concurrency/InvoiceNumberingTest`** | **3** | Sequence under contention |

`Concurrency` is a separate PHPUnit suite using `DatabaseTruncation`.
`RefreshDatabase` wraps each test in one transaction, so a second connection
would not see the writes and the tests would pass for the wrong reason.

**Dependencies** — the six approved packages (medialibrary, permission,
sluggable, sitemap, dompdf, pest) plus exactly one addition:
`filament/spatie-laravel-media-library-plugin`. It is first-party Filament,
version-locked to `filament/support` via `self.version`, and it is the official
adapter between two packages already approved. Without it every resource needs
hand-rolled upload-to-media sync.

---

## 3. Three deviations from the original brief

Stated up front because they are the first things worth querying.

**1. Laravel 13 + Filament 5, not Laravel 12 + Filament v4.**
Both pins in the brief were a generation behind by September 2026. Laravel
13.32 and Filament 5.8 are current; Filament 4 and 5 both support Laravel
11.28/12/13, the difference being Livewire 3 vs 4. Laravel 13 also matches the
major version in `ceritaconvo-booking-system`, so the two codebases stay
siblings.

**2. There is no `tailwind.config.js`.**
Tailwind v4 is CSS-first and removed it. The brief's actual requirement — every
colour, font, spacing step and radius in one place, backed by CSS custom
properties, restyle the whole site from one file — is served by the `@theme`
block in [`resources/css/tokens.css`](resources/css/tokens.css). Same
guarantee, one fewer file, and it is the supported path.

**3. The booking wizard validates through Livewire's `rules()`, not a
`FormRequest`.**
The brief asked for Form Requests everywhere. The wizard is a Livewire
component, which validates through its own `rules()`. Rather than have two
definitions of a valid booking, the rules live in one shared class,
[`app/Http/Requests/BookingRules.php`](app/Http/Requests/BookingRules.php), and
both paths consume it. The contact form, which is a plain HTML POST, does use a
real `FormRequest` — and therefore works with JavaScript disabled.

---

## 4. What was built, phase by phase

### Phase 1 — Foundation

Design tokens, the motion system, the layout shell, a Blade component library,
and roles.

- [`resources/css/tokens.css`](resources/css/tokens.css) — one `@theme` block
  defining every colour, font, fluid `clamp()` type step, spacing step and
  radius. No hex value exists anywhere else in the codebase.
- [`resources/js/motion.js`](resources/js/motion.js) — every scroll-linked
  effect in one module, which is what makes three rules enforceable in one
  place: `prefers-reduced-motion` kills everything, parallax and the custom
  cursor drop below 768px, and every ScrollTrigger is torn down on navigation.
- Reveal targets start at `opacity: 0`, which is only safe while JS runs. An
  inline head script sets `data-motion="on"` before first paint *and* arms a
  2.5s failsafe that strips it if the bundle never loads — so a broken build
  degrades to plain readable HTML rather than a blank page.
- `spatie/laravel-permission` with three roles: `super_admin`, `admin`, `staff`.
- Every user-facing string routed through `__()` from the first file.

### Phase 2 — Content & portfolio

Projects, testimonials, journal posts, owner-editable settings, the homepage
and `/work`.

- Images live in the media library, not columns — that is what gives
  drag-to-reorder galleries and automatic WebP conversions (thumb / grid / full,
  original always preserved).
- Placeholder photography is **generated locally** by
  `php artisan corememory:placeholders` — real PNGs drawn with GD, not shipped
  files and nothing hotlinked. Rasters rather than SVGs specifically so the
  conversion pipeline is genuinely exercised by seeding instead of silently
  skipped.
- Settings are a key/value table behind a cached accessor
  ([`app/Support/Settings.php`](app/Support/Settings.php)) rather than a JSON
  singleton, so two admins editing different fields cannot clobber each other.
  No extra package.

### Phase 3 — Packages, availability & booking

The heart of the system. Detail in [CLAUDE.md § Availability](CLAUDE.md).

- `/packages` — comparison table on desktop, stacked cards on mobile, every
  inclusion explicit. Prices are visible with zero interaction; that is the
  entire premise.
- [`app/Services/AvailabilityService.php`](app/Services/AvailabilityService.php)
  — the engine. Two queries for a whole month, never one per day.
- [`app/Livewire/BookingWizard.php`](app/Livewire/BookingWizard.php) — date and
  slot chosen **first**, so a clash surfaces before the couple invests effort.
  State persists in the session, not just component state, so a refresh at
  midnight while comparing studios doesn't lose progress.
- Multi-date bookings: a nikah and a reception are one booking under one
  reference, which is why `booking_dates` exists instead of a single
  `event_date` column.
- Honeypot plus rate limiting, no CAPTCHA. Queued client and studio emails
  carrying the required availability disclaimer.
- `bookings:expire-stale` on the scheduler cancels pending enquiries nobody
  answered after 14 days.

### Phase 4 — Invoicing & finance

- One click from a booking to a deposit or final invoice, plus standalone
  invoices for walk-ins.
- PDFs via dompdf. An invoice is a letterhead and a bordered table — no
  flexbox, no grid, no JS — so the VPS needs no headless Chromium (~400 MB plus
  a class of queue-worker failure modes). Stored on the private disk and
  streamed; clients get a signed, expiring URL with no login, because they have
  no account.
- Payments recorded with method, reference and an optional receipt upload.
  Direct costs per booking as a repeater.
- [`app/Services/FinanceReport.php`](app/Services/FinanceReport.php) — the
  dashboard. Every figure computed in SQL, none by looping models.

### Phase 5 — Polish

- `/about`, `/journal`, `/journal/{slug}`, `/contact` with a real `FormRequest`
  on a plain POST.
- SEO: `LocalBusiness` (as the more specific `Photograph` subtype) site-wide,
  `Service` with real MYR prices on `/packages`, `Article` on posts,
  `ImageGallery` on wedding stories, breadcrumbs, canonical and Open Graph
  everywhere, a cached sitemap and `robots.txt`. `/book` and `/availability` are
  excluded from both — they are application steps, not content.
- LQIP blur-up placeholders: a 24px blurred WebP inlined as a data URI, cached
  by media id and mtime so replacing an image invalidates its placeholder
  automatically.
- Accessibility: contrast computed rather than eyeballed — see §7.
- [DEPLOYMENT.md](DEPLOYMENT.md) written.

---

## 5. Four decisions worth challenging

### Slot expansion

`full_day` is **never stored**. It expands into the three concrete slots, so a
single `UNIQUE(event_date, session_slot)` on `slot_holds` enforces the entire
conflict matrix with no application logic that can get it wrong:

| Scenario | Outcome |
| --- | --- |
| Full Day onto a date with an existing Morning | collides on the morning row |
| Morning onto an existing Full Day | collides on the morning row |
| The same slot twice | collides |
| Morning + Evening on one date | both succeed — correct |
| Admin block vs confirmed booking | collides — one shared namespace |

`AvailabilityService::hold()` deliberately performs **no pre-check**. Checking
then inserting leaves a race window between the two; writing and letting the
unique index reject the loser closes it. MySQL error 1062 becomes
`SlotUnavailableException`.

Holds exist only for blocking statuses. MySQL has no partial indexes, which is
precisely why holds live in their own table rather than as an index on
`bookings`.

Proven by `tests/Concurrency/DoubleBookingTest.php`, which opens two real
database connections and races them. A sequential test would pass even if the
protection were only an application-level check — which would still lose the
race in production.

### Pending is tentative, not held

Two couples may enquire for the same slot; the studio decides between them. The
hard guarantee lands at **confirm**, not at submit.

This resolves a genuine contradiction in the brief, which asked both for
"pending shows as tentative" and "two people hitting submit at the same second
must not both succeed". Those cannot both be true at submit time. So: submit
rejects plainly if the slot became *blocked* while a tab was stale, and confirm
carries the database-level guarantee.

Blocking statuses live in `config/booking.php`. Adding `pending` to that array
moves the hard guarantee to submit time with **no code change** — holds are
written wherever the status qualifies.

### Invoices are snapshots, not views

Line items copy the prices captured when the couple booked; client details are
copied too. Correcting a booking must never rewrite an invoice already sitting
in someone's inbox. Items lock (`locked_at`) the moment status leaves draft.

Deposit and final reconcile to the contract value, each carrying the full scope
plus one negative deduction line:

| Deposit | | Final | |
| --- | ---: | --- | ---: |
| Package | 6,800.00 | Package | 6,800.00 |
| Extra hour ×3 | 1,350.00 | Extra hour ×3 | 1,350.00 |
| *Less: balance after event* | −5,705.00 | *Less: deposit invoiced* | −2,445.00 |
| **Due now** | **2,445.00** | **Due** | **5,705.00** |

Hand a client both documents and the arithmetic holds. That is why invoice
money columns are **signed** integers.

Overdue is **computed**, never stored — a stored flag needs a nightly job to
stay true and is wrong for every hour between the due date passing and that job
running.

### Money is integer cents behind a value object

[`app/ValueObjects/Money.php`](app/ValueObjects/Money.php) and
[`app/Casts/Money.php`](app/Casts/Money.php) were adopted from
`ceritaconvo-booking-system` so the two projects share their money handling.

One thing was deliberately **not** carried over. That repo's
`InvoiceService::generate()` derives the invoice number from
`Invoice::count() + 1`. Two consequences: deleting an invoice frees a number
that has already been sent to a client, and two simultaneous requests read the
same count and produce a duplicate. Here the number comes from a locked
`sequences` row taken inside the same transaction as the invoice
(`SELECT ... FOR UPDATE`), which is what "sequential, never reused" actually
requires. Both failure modes are pinned by
`tests/Concurrency/InvoiceNumberingTest.php`.

Worth mentioning because the same pattern is live in the convo site.

---

## 6. Traps already hit

All framework-interaction bugs. All of them pass code review and break later,
which is the only reason they are worth writing down.

| Trap | Symptom |
| --- | --- |
| **Caching Eloquent models or collections** | `__PHP_Incomplete_Class` on the **second** read. Green on the first call, broken once the cache warms. Hit twice — Phase 2 and again in Phase 4 |
| **`Money` cast without `SerializesCastableAttributes`** | `attributesToArray()` returns the object, it lands in Livewire's component state, and every Filament **edit** page dies with "Property type not supported" — including for columns with no form field to intercept it |
| **Filament `->numeric()`** | Installs a state cast that runs `floatval()` on the value *before* `formatStateUsing` sees it. Fatal on a value object. All admin money fields now go through one `MoneyInput` component |
| **`Collection::sum('amount_cents')`** | Adds `Money` **objects** together. Fatal. Query-builder sums are fine — those happen in SQL and never touch the cast |
| **Narrow `CarbonImmutable` type hints** | Reject `now()`, which returns a mutable `Illuminate\Support\Carbon`. Hit twice, in two different services |
| **`with('holdable.booking')` across a morph** | `BlockedDate` has no `booking` relation. Needs `morphWith` |
| **PHPUnit's 128M default** | Image conversions hold whole bitmaps in memory; the suite died mid-run with a fatal pointing at an unrelated file. `phpunit.xml` now sets 512M |

Two testing rules came out of these, and both are now enforced:

- **Always test Filament *edit* pages.** Edit is the only place a form hydrates
  an existing record. Index and create pages start empty and cannot reach
  hydration bugs — two of the above were invisible until edit pages were tested.
- **Always exercise a cached read twice.** Object-serialisation bugs only appear
  on the second call, so they ship green and break five minutes later.

Also worth knowing: `Model::preventLazyLoading()` is on in local development, so
a missing eager-load throws rather than quietly costing a query per row.

---

## 7. Accessibility

Colour contrast was **computed, not eyeballed**, and three tokens failed WCAG:

| Token | Was | Now | Why it mattered |
| --- | ---: | ---: | --- |
| `--color-ink-muted` | 3.36:1 | **4.93:1** | Colours every micro-label on the site, and those are 10–11px — small text, needs 4.5:1 |
| `--color-ink-faint` | 2.05:1 | **3.15:1** | Decorative only |
| `--color-accent-soft` | 4.14:1 | **5.39:1** | Footer links, checked against the *inverse* surface rather than paper |

`--color-input-border` was added separately from `--color-rule`: a form
control's edge is how you find the field (WCAG 1.4.11, 3:1) while a divider is
decorative. **Inputs now read slightly heavier than the hairline rules** — a
visible change to the design, and the correct trade.

Also pinned by `ContentPagesTest`: one `<h1>` per page, no heading-level skips,
`alt` on every image, `aria-describedby` linking form errors to their input, a
skip link, and `aria-current` on active navigation.

---

## 8. What is not done

### Blocking a production launch

- **Never deployed.** [DEPLOYMENT.md](DEPLOYMENT.md) is written but unexecuted.
  Nothing in it has been run against a real server.
- **All content is placeholder** — copy, prices, photographs. Including the
  invoice business details: the SSM number and bank account in settings are
  demo values. An invoice carrying a placeholder SSM number must not reach a
  client.
- **`public/favicon.ico` is 0 bytes** — Laravel's empty default, never replaced.

### Brief items not finished

- **Lighthouse was never run.** The work it measures is done — srcset, lazy
  below the fold, explicit dimensions, LQIP, self-hosted fonts, no N+1, ~8 KB
  gzipped CSS — and `PerformanceTest` pins it as a regression suite. But the
  ≥90 mobile / ≥95 accessibility targets are **unverified**. Needs a production
  build on the VPS where caching headers are live.
- **No real-device testing.** The custom cursor and the sub-768px parallax
  gating are correct in code and covered by tests, but have not been seen on an
  actual phone. Most of this site's traffic will arrive from an Instagram bio
  link on a phone, so this is worth doing before launch.
- **No `seo.og_image` setting.** The brief asked for an editable OG image in
  admin settings. `Seo::defaultImage()` currently derives one from a featured
  project instead. Adding the key is small; it was missed.
- **No `lang/ms/`.** Every string goes through `__()` and `lang/en/` has 8
  files, so a Bahasa Malaysia locale is a file drop with no Blade changes — but
  zero BM strings exist today.

### Deliberately out of scope

- **Payment gateway.** The `payments` table carries nullable `gateway`,
  `gateway_reference` and `raw_response`, so ToyyibPay or Billplz needs a
  webhook handler and **no migration**. This is the obvious place a second
  opinion helps most — the convo site has already solved it once.

### Cleanup

- **`AGENTS.md` should be deleted.** It is Laravel's stale Boost bootstrap file,
  and it instructs a developer to `composer require laravel/boost` — a package
  deliberately *not* installed here, because the brief required new dependencies
  to be justified first. Anyone opening the repo cold would follow it.

---

## 9. Where to look

The dozen files that carry the weight:

| File | Why |
| --- | --- |
| [`app/Services/AvailabilityService.php`](app/Services/AvailabilityService.php) | The engine. Read this first |
| [`app/Enums/SessionSlot.php`](app/Enums/SessionSlot.php) | `expand()` — the one place full-day becomes three slots |
| [`app/Actions/Bookings/ConfirmBooking.php`](app/Actions/Bookings/ConfirmBooking.php) | Where the double-booking guarantee lives |
| [`app/Actions/Bookings/CreateBooking.php`](app/Actions/Bookings/CreateBooking.php) | The public submit path |
| [`app/Actions/Invoices/GenerateInvoice.php`](app/Actions/Invoices/GenerateInvoice.php) | Snapshot construction and number allocation |
| [`app/Services/FinanceReport.php`](app/Services/FinanceReport.php) | Every dashboard figure, all in SQL |
| [`app/Livewire/BookingWizard.php`](app/Livewire/BookingWizard.php) | The public flow |
| [`app/ValueObjects/Money.php`](app/ValueObjects/Money.php) · [`app/Casts/Money.php`](app/Casts/Money.php) | Integer cents |
| [`resources/css/tokens.css`](resources/css/tokens.css) | The whole design system |
| [`resources/js/motion.js`](resources/js/motion.js) | All scroll-linked animation |
| [`tests/Concurrency/`](tests/Concurrency/) | The guarantees, proven rather than asserted |
| `config/booking.php` | Blocking statuses, booking window, lapse period |

**Never assign `$booking->status` directly.** Use
`App\Actions\Bookings\ChangeBookingStatus`. Assigning it changes the badge in
the UI and leaves `slot_holds` untouched — a cancelled wedding keeps its
Saturday, or a confirmed one never reserves it. The admin panel's status field
is read-only for exactly this reason.

---

## 10. Running it

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
./scripts/db start
php artisan migrate:fresh --seed && php artisan storage:link
npm run dev          # terminal 1
php artisan serve    # terminal 2
```

Admin at `/admin` — `owner@corememory.test` / `password` (seeded, local only).

Two local peculiarities worth knowing:

- **PHP comes from [php.new](https://php.new) (Herd Lite)**, installed to
  `~/.config/herd-lite/bin`. XAMPP on this machine ships PHP 7.4, which cannot
  run Laravel 13. If `php -v` shows 7.4, XAMPP is ahead on `PATH`.
- **The database is a project-owned MariaDB on port 3307**, managed by
  `./scripts/db start|stop|status|shell|reset`. It runs as the logged-in user
  from its own data directory, so it needs no `sudo` and does not disturb
  XAMPP's MySQL on 3306. Switching to XAMPP is a `DB_PORT` change and nothing
  else.

Full detail, including troubleshooting, in [README.md](README.md).

```bash
php artisan test                          # 245 tests
php artisan test --testsuite=Concurrency  # the double-booking guarantees
./vendor/bin/pint                         # format before committing
```

Tests run against the `corememory_testing` **MySQL** database, not SQLite. That
costs some speed and is deliberate: the availability guarantee is a real unique
index and MySQL error 1062. SQLite raises a different error and does not
reproduce the concurrent-submit race at all, so an in-memory suite would pass
happily while the real double-booking bug shipped.

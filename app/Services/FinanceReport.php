<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\InvoiceStatus;
use App\ValueObjects\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Every figure on the finance dashboard.
 *
 * TWO RULES THIS CLASS EXISTS TO ENFORCE:
 *
 * 1. INVOICED IS NOT COLLECTED. An invoice raised is not money in the bank.
 *    Conflating them is how a studio convinces itself it had a good month.
 *    Every revenue figure here declares which basis it is on:
 *      - CASH    — payments actually received in the period
 *      - ACCRUAL — invoices issued in the period, paid or not
 *
 * 2. AGGREGATE IN SQL, NOT IN PHP. Nothing here loads a collection of models
 *    and loops it. On a studio with a few thousand bookings that is the
 *    difference between a dashboard and a timeout.
 *
 * Results are cached briefly — the owner refreshing twice in a minute should
 * not re-run a dozen aggregates, but the numbers must not feel stale either.
 */
class FinanceReport
{
    public const BASIS_CASH = 'cash';

    public const BASIS_ACCRUAL = 'accrual';

    private const CACHE_TTL_SECONDS = 300;

    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly string $basis = self::BASIS_CASH,
    ) {}

    /**
     * Accepts Carbon, CarbonImmutable or a plain date string.
     *
     * `now()` returns a MUTABLE Illuminate\Support\Carbon, so pinning this to
     * CarbonImmutable would reject the most obvious way to call it. Parse once
     * here instead of making every caller convert.
     */
    public static function for(
        CarbonInterface|string|null $from = null,
        CarbonInterface|string|null $to = null,
        string $basis = self::BASIS_CASH,
    ): self {
        return new self(
            $from ? CarbonImmutable::parse($from)->startOfDay() : CarbonImmutable::today()->startOfMonth(),
            $to ? CarbonImmutable::parse($to)->endOfDay() : CarbonImmutable::today()->endOfMonth(),
            $basis,
        );
    }

    /** The same window, shifted back by its own length — for "vs last period". */
    public function previousPeriod(): self
    {
        $length = max(1, $this->from->diffInDays($this->to) + 1);

        return new self(
            $this->from->subDays($length),
            $this->from->subDay()->endOfDay(),
            $this->basis,
        );
    }

    public function basisLabel(): string
    {
        return $this->basis === self::BASIS_CASH
            ? __('finance.basis.cash')
            : __('finance.basis.accrual');
    }

    /*
    |--------------------------------------------------------------------------
    | Revenue
    |--------------------------------------------------------------------------
    */

    /** Money actually received in the period. */
    public function collected(): Money
    {
        return $this->money('collected', fn (): int => (int) DB::table('payments')
            ->whereBetween('paid_at', [$this->from->toDateString(), $this->to->toDateString()])
            ->sum('amount_cents'));
    }

    /** Money invoiced in the period, paid or not. Drafts never count. */
    public function invoiced(): Money
    {
        return $this->money('invoiced', fn (): int => (int) DB::table('invoices')
            ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Paid->value])
            ->whereBetween('issued_at', [$this->from->toDateString(), $this->to->toDateString()])
            ->sum('total_cents'));
    }

    /** Revenue on whichever basis is selected. */
    public function revenue(): Money
    {
        return $this->basis === self::BASIS_CASH ? $this->collected() : $this->invoiced();
    }

    /*
    |--------------------------------------------------------------------------
    | Costs and profit
    |--------------------------------------------------------------------------
    */

    /**
     * Direct costs of bookings whose EVENT falls in the period.
     *
     * Dated by the event rather than by when the cost row was entered, so a
     * cost keyed in late still lands in the month the work happened and the
     * margin for that month stays honest.
     */
    public function costs(): Money
    {
        return $this->money('costs', fn (): int => (int) DB::table('booking_costs')
            ->join('bookings', 'bookings.id', '=', 'booking_costs.booking_id')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('booking_dates')
                ->whereColumn('booking_dates.booking_id', 'bookings.id')
                ->whereBetween('booking_dates.event_date', [$this->from->toDateString(), $this->to->toDateString()]))
            ->sum('booking_costs.amount_cents'));
    }

    public function grossProfit(): Money
    {
        return $this->revenue()->minus($this->costs());
    }

    /** Null rather than zero when there is no revenue — 0% would be a lie. */
    public function grossMarginPercent(): ?float
    {
        $revenue = $this->revenue();

        if ($revenue->isZero()) {
            return null;
        }

        return round($this->grossProfit()->cents / $revenue->cents * 100, 1);
    }

    /*
    |--------------------------------------------------------------------------
    | Receivables
    |--------------------------------------------------------------------------
    */

    /**
     * What is still owed across ALL sent invoices, ignoring the date filter.
     *
     * Receivables are a position, not a period: money owed from four months
     * ago is still owed today, and hiding it behind a date range is how it
     * gets forgotten.
     *
     * @return array{total: Money, buckets: array<string, Money>, count: int}
     */
    public function receivables(): array
    {
        /*
         * Only PRIMITIVES cross the cache boundary — see CLAUDE.md § Caching.
         * Caching the Money objects directly comes back as
         * __PHP_Incomplete_Class on the second read, and the dashboard dies
         * five minutes after it first worked. Cents in, Money out.
         */
        $cached = Cache::remember($this->key('receivables'), self::CACHE_TTL_SECONDS, function (): array {
            // outstanding = total - payments, computed in SQL.
            $rows = DB::table('invoices')
                ->leftJoin('payments', 'payments.invoice_id', '=', 'invoices.id')
                ->where('invoices.status', InvoiceStatus::Sent->value)
                ->groupBy('invoices.id', 'invoices.due_at', 'invoices.total_cents')
                ->select([
                    'invoices.due_at',
                    DB::raw('invoices.total_cents - COALESCE(SUM(payments.amount_cents), 0) AS outstanding'),
                ])
                ->havingRaw('outstanding > 0')
                ->get();

            $buckets = ['0-30' => 0, '31-60' => 0, '60+' => 0, 'not_due' => 0];
            $total = 0;

            foreach ($rows as $row) {
                $outstanding = (int) $row->outstanding;
                $total += $outstanding;

                $dueAt = $row->due_at ? CarbonImmutable::parse($row->due_at) : null;

                if ($dueAt === null || $dueAt->isFuture()) {
                    $buckets['not_due'] += $outstanding;

                    continue;
                }

                $days = (int) $dueAt->diffInDays(CarbonImmutable::today());

                $bucket = match (true) {
                    $days <= 30 => '0-30',
                    $days <= 60 => '31-60',
                    default => '60+',
                };

                $buckets[$bucket] += $outstanding;
            }

            return [
                'total' => $total,
                'buckets' => $buckets,
                'count' => $rows->count(),
            ];
        });

        return [
            'total' => new Money($cached['total']),
            'buckets' => array_map(fn (int $cents) => new Money($cents), $cached['buckets']),
            'count' => $cached['count'],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Bookings
    |--------------------------------------------------------------------------
    */

    /** @return array<string, int> status => count, for enquiries made in the period */
    public function bookingsByStatus(): array
    {
        return Cache::remember($this->key('by_status'), self::CACHE_TTL_SECONDS, fn (): array => DB::table('bookings')
            ->whereBetween('created_at', [$this->from, $this->to])
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) AS total')
            ->pluck('total', 'status')
            ->all());
    }

    /**
     * The funnel: every enquiry, how many were quoted, confirmed, completed.
     *
     * Cumulative rather than exclusive — a completed booking was also once
     * confirmed, so counting it only in the last bucket would understate every
     * earlier stage and make conversion look worse than it was.
     *
     * @return array<string, int>
     */
    public function funnel(): array
    {
        $byStatus = $this->bookingsByStatus();
        $count = fn (BookingStatus ...$statuses): int => array_sum(
            array_map(fn (BookingStatus $s) => $byStatus[$s->value] ?? 0, $statuses)
        );

        $enquiries = array_sum($byStatus);

        return [
            'enquiries' => $enquiries,
            'quoted' => $count(
                BookingStatus::Quoted, BookingStatus::Confirmed,
                BookingStatus::DepositPaid, BookingStatus::Completed,
            ),
            'confirmed' => $count(
                BookingStatus::Confirmed, BookingStatus::DepositPaid, BookingStatus::Completed,
            ),
            'completed' => $count(BookingStatus::Completed),
        ];
    }

    /** Enquiry → confirmed, as a percentage. Null when there were no enquiries. */
    public function conversionRate(): ?float
    {
        $funnel = $this->funnel();

        if ($funnel['enquiries'] === 0) {
            return null;
        }

        return round($funnel['confirmed'] / $funnel['enquiries'] * 100, 1);
    }

    /** Average value of bookings taken in the period. */
    public function averageBookingValue(): Money
    {
        return $this->money('avg_booking', fn (): int => (int) round((float) DB::table('bookings')
            ->whereBetween('created_at', [$this->from, $this->to])
            ->whereNotIn('status', [BookingStatus::Cancelled->value])
            ->avg('estimated_total_cents')));
    }

    /*
    |--------------------------------------------------------------------------
    | Breakdowns
    |--------------------------------------------------------------------------
    */

    /** @return Collection<int, object{name: string, bookings: int, revenue: Money}> */
    public function revenueByPackage(): Collection
    {
        // Plain arrays into the cache; Money built after. See receivables().
        $rows = Cache::remember($this->key('by_package'), self::CACHE_TTL_SECONDS, fn (): array => DB::table('bookings')
            ->join('packages', 'packages.id', '=', 'bookings.package_id')
            ->whereBetween('bookings.created_at', [$this->from, $this->to])
            ->whereNotIn('bookings.status', [BookingStatus::Cancelled->value])
            ->groupBy('packages.id', 'packages.name')
            ->select([
                'packages.name',
                DB::raw('COUNT(*) AS bookings'),
                DB::raw('SUM(bookings.estimated_total_cents) AS revenue'),
            ])
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row): array => [
                'name' => (string) $row->name,
                'bookings' => (int) $row->bookings,
                'revenue' => (int) $row->revenue,
            ])
            ->all());

        return collect($rows)->map(fn (array $row) => (object) [
            'name' => $row['name'],
            'bookings' => $row['bookings'],
            'revenue' => new Money($row['revenue']),
        ]);
    }

    /**
     * Which add-ons couples actually take.
     *
     * @return Collection<int, object{name: string, taken: int, attach_rate: float, revenue: Money}>
     */
    public function addOnAttachRate(): Collection
    {
        $rows = Cache::remember($this->key('addon_attach'), self::CACHE_TTL_SECONDS, function (): array {
            $totalBookings = (int) DB::table('bookings')
                ->whereBetween('created_at', [$this->from, $this->to])
                ->whereNotIn('status', [BookingStatus::Cancelled->value])
                ->count();

            return DB::table('booking_add_on')
                ->join('add_ons', 'add_ons.id', '=', 'booking_add_on.add_on_id')
                ->join('bookings', 'bookings.id', '=', 'booking_add_on.booking_id')
                ->whereBetween('bookings.created_at', [$this->from, $this->to])
                ->whereNotIn('bookings.status', [BookingStatus::Cancelled->value])
                ->groupBy('add_ons.id', 'add_ons.name')
                ->select([
                    'add_ons.name',
                    DB::raw('COUNT(DISTINCT booking_add_on.booking_id) AS taken'),
                    DB::raw('SUM(booking_add_on.line_total_cents) AS revenue'),
                ])
                ->orderByDesc('taken')
                ->get()
                ->map(fn ($row): array => [
                    'name' => (string) $row->name,
                    'taken' => (int) $row->taken,
                    'attach_rate' => $totalBookings > 0
                        ? round((int) $row->taken / $totalBookings * 100, 1)
                        : 0.0,
                    'revenue' => (int) $row->revenue,
                ])
                ->all();
        });

        return collect($rows)->map(fn (array $row) => (object) [
            'name' => $row['name'],
            'taken' => $row['taken'],
            'attach_rate' => $row['attach_rate'],
            'revenue' => new Money($row['revenue']),
        ]);
    }

    /** @return array<string, int> source => count */
    public function sourceBreakdown(): array
    {
        return Cache::remember($this->key('sources'), self::CACHE_TTL_SECONDS, fn (): array => DB::table('bookings')
            ->whereBetween('created_at', [$this->from, $this->to])
            ->groupBy('source')
            ->selectRaw('COALESCE(source, \'unknown\') AS source, COUNT(*) AS total')
            ->pluck('total', 'source')
            ->all());
    }

    /*
    |--------------------------------------------------------------------------
    | Twelve-month series
    |--------------------------------------------------------------------------
    */

    /**
     * Revenue, cost and profit by month for the headline chart.
     *
     * Three grouped queries, then merged in PHP over at most twelve rows —
     * not one query per month.
     *
     * @return array{labels: list<string>, revenue: list<float>, costs: list<float>, profit: list<float>}
     */
    public function monthlySeries(int $months = 12): array
    {
        return Cache::remember($this->key("series_{$months}"), self::CACHE_TTL_SECONDS, function () use ($months): array {
            $start = CarbonImmutable::today()->subMonths($months - 1)->startOfMonth();
            $end = CarbonImmutable::today()->endOfMonth();

            $revenue = $this->basis === self::BASIS_CASH
                ? DB::table('payments')
                    ->whereBetween('paid_at', [$start->toDateString(), $end->toDateString()])
                    ->groupBy('month')
                    ->selectRaw("DATE_FORMAT(paid_at, '%Y-%m') AS month, SUM(amount_cents) AS total")
                    ->pluck('total', 'month')
                : DB::table('invoices')
                    ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Paid->value])
                    ->whereBetween('issued_at', [$start->toDateString(), $end->toDateString()])
                    ->groupBy('month')
                    ->selectRaw("DATE_FORMAT(issued_at, '%Y-%m') AS month, SUM(total_cents) AS total")
                    ->pluck('total', 'month');

            $costs = DB::table('booking_costs')
                ->join('bookings', 'bookings.id', '=', 'booking_costs.booking_id')
                ->join('booking_dates', 'booking_dates.booking_id', '=', 'bookings.id')
                ->whereBetween('booking_dates.event_date', [$start->toDateString(), $end->toDateString()])
                ->groupBy('month')
                ->selectRaw("DATE_FORMAT(booking_dates.event_date, '%Y-%m') AS month, SUM(booking_costs.amount_cents) AS total")
                ->pluck('total', 'month');

            $labels = [];
            $revenueSeries = [];
            $costSeries = [];
            $profitSeries = [];

            for ($cursor = $start; $cursor <= $end; $cursor = $cursor->addMonth()) {
                $key = $cursor->format('Y-m');

                $monthRevenue = (int) ($revenue[$key] ?? 0);
                $monthCosts = (int) ($costs[$key] ?? 0);

                $labels[] = $cursor->translatedFormat('M Y');
                $revenueSeries[] = round($monthRevenue / 100, 2);
                $costSeries[] = round($monthCosts / 100, 2);
                $profitSeries[] = round(($monthRevenue - $monthCosts) / 100, 2);
            }

            return [
                'labels' => $labels,
                'revenue' => $revenueSeries,
                'costs' => $costSeries,
                'profit' => $profitSeries,
            ];
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    private function money(string $key, callable $resolver): Money
    {
        return new Money(
            (int) Cache::remember($this->key($key), self::CACHE_TTL_SECONDS, $resolver)
        );
    }

    /** Cache key includes the window and basis, so filters never collide. */
    private function key(string $suffix): string
    {
        return sprintf(
            'finance.%s.%s.%s.%s',
            $suffix,
            $this->from->toDateString(),
            $this->to->toDateString(),
            $this->basis,
        );
    }

    /*
     * No explicit cache busting here, deliberately.
     *
     * Not every cache driver supports tags, so the only way to invalidate
     * these keys by hand would be Cache::flush() — which would also throw away
     * the homepage payload and the settings cache, punishing the whole site
     * every time a payment is recorded.
     *
     * The dashboard does not need instant consistency the way the public site
     * does. Five minutes late on a revenue figure is fine; a cleared homepage
     * cache on every keystroke is not. Hence the short TTL and no flush.
     */
}

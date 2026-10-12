<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Invoice;
use App\Services\FinanceReport;
use App\ValueObjects\Money;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Sales & profit.
 *
 * One page with ONE global filter — a date range and a revenue basis — that
 * every figure on it obeys. Widgets each carrying their own period is how a
 * dashboard ends up showing four numbers that cannot be reconciled with each
 * other.
 *
 * The cash/accrual toggle is labelled in words rather than jargon, and the
 * active basis is printed next to every revenue figure. An owner should never
 * have to guess whether a number is money received or money invoiced.
 *
 * It is also the admin's home page (/admin): the studio is two people who both
 * run the books, so owner and staff alike land on their numbers. Invoices and
 * Settings stay owner-only; this page only reads them.
 */
class FinanceDashboard extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.finance-dashboard';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $navigationLabel = 'Dashboard';

    protected static string|UnitEnum|null $navigationGroup = null;

    // Above every resource, where Filament's own Dashboard used to sit.
    protected static ?int $navigationSort = -2;

    protected static ?string $title = 'Dashboard';

    /** @var array<string, mixed> */
    public array $filters = [];

    public const PRESETS = ['this_month', 'last_month', 'this_year', 'last_12_months'];

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    /** The panel's home: /admin itself, as Filament's stock Dashboard was. */
    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    public function getSubheading(): string
    {
        $report = $this->report;

        return __('finance.dashboard.subheading', [
            'from' => $report->from->format('j M Y'),
            'to' => $report->to->format('j M Y'),
            'basis' => $report->basisLabel(),
        ]);
    }

    public function mount(): void
    {
        $this->filtersForm->fill([
            'from' => CarbonImmutable::today()->startOfMonth()->toDateString(),
            'to' => CarbonImmutable::today()->endOfMonth()->toDateString(),
            'basis' => FinanceReport::BASIS_CASH,
        ]);
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                // A plain grid: the view wraps it in one card with the presets.
                Grid::make(['default' => 1, 'sm' => 3])
                    ->schema([
                        DatePicker::make('from')
                            ->label(__('finance.period.from'))
                            ->native(false)
                            ->displayFormat('d M Y')
                            ->live(),

                        DatePicker::make('to')
                            ->label(__('finance.period.to'))
                            ->native(false)
                            ->displayFormat('d M Y')
                            ->live(),

                        Select::make('basis')
                            ->label(__('finance.basis.label'))
                            ->options([
                                FinanceReport::BASIS_CASH => __('finance.basis.cash'),
                                FinanceReport::BASIS_ACCRUAL => __('finance.basis.accrual'),
                            ])
                            ->required()
                            ->native(false)
                            ->live()
                            ->helperText(__('finance.basis.help')),
                    ]),
            ])
            ->statePath('filters');
    }

    /** Quick presets, so the common periods are one click. */
    public function applyPreset(string $preset): void
    {
        [$from, $to] = self::presetRange($preset);

        $this->filters['from'] = $from->toDateString();
        $this->filters['to'] = $to->toDateString();
    }

    /** @return array<int, CarbonImmutable> [from, to] */
    private static function presetRange(string $preset): array
    {
        $today = CarbonImmutable::today();

        return match ($preset) {
            'last_month' => [$today->subMonth()->startOfMonth(), $today->subMonth()->endOfMonth()],
            'this_year' => [$today->startOfYear(), $today->endOfYear()],
            'last_12_months' => [$today->subMonths(11)->startOfMonth(), $today->endOfMonth()],
            default => [$today->startOfMonth(), $today->endOfMonth()],
        };
    }

    /** The preset the current dates match, so its button can show as chosen. */
    public function getActivePresetProperty(): ?string
    {
        foreach (self::PRESETS as $preset) {
            [$from, $to] = self::presetRange($preset);

            // Date part only: the non-native picker can hold a time as well.
            if (substr((string) ($this->filters['from'] ?? ''), 0, 10) === $from->toDateString()
                && substr((string) ($this->filters['to'] ?? ''), 0, 10) === $to->toDateString()) {
                return $preset;
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    */

    public function getReportProperty(): FinanceReport
    {
        return FinanceReport::for(
            $this->filters['from'] ?? null,
            $this->filters['to'] ?? null,
            $this->filters['basis'] ?? FinanceReport::BASIS_CASH,
        );
    }

    public function getPreviousReportProperty(): FinanceReport
    {
        return $this->report->previousPeriod();
    }

    /**
     * Period-over-period change, as a percentage.
     *
     * Null when the previous period was zero — "up 100%" from nothing is
     * meaningless and reads as a real result.
     */
    public function change(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return null;
        }

        return round(($current - $previous) / abs($previous) * 100, 1);
    }

    /** Events in the next 30 days, for the diary panel. */
    public function getUpcomingProperty(): Collection
    {
        return Booking::query()
            ->whereIn('status', [
                BookingStatus::Confirmed->value,
                BookingStatus::DepositPaid->value,
            ])
            ->whereHas('dates', fn ($q) => $q
                ->whereDate('event_date', '>=', today())
                ->whereDate('event_date', '<=', today()->addDays(30)))
            ->with(['dates', 'package'])
            ->get()
            ->sortBy(fn (Booking $booking) => $booking->primaryDate()?->event_date)
            ->values();
    }

    /**
     * What needs doing, ignoring the period filter: a request waiting for a
     * reply is waiting whatever month the owner happens to be looking at.
     *
     * @return array{pending: int, upcoming: int, overdue_count: int, overdue_total: string}
     */
    public function getAttentionProperty(): array
    {
        $overdue = $this->overdueInvoices;

        return [
            'pending' => Booking::query()->where('status', BookingStatus::Pending->value)->count(),
            'upcoming' => $this->upcoming->count(),
            'overdue_count' => $overdue->count(),
            'overdue_total' => (new Money((int) $overdue->sum(fn (Invoice $i) => $i->outstanding()->cents)))->formatCompact(),
        ];
    }

    public function getOverdueInvoicesProperty(): Collection
    {
        return Invoice::query()
            ->overdue()
            ->with(['payments', 'booking'])
            ->get()
            // Outstanding depends on payments, so the sort happens after
            // loading — this list is small by nature.
            ->filter(fn (Invoice $invoice) => ! $invoice->isSettled())
            ->sortByDesc(fn (Invoice $invoice) => $invoice->daysOverdue())
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Export
    |--------------------------------------------------------------------------
    */

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export summary to CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn (): StreamedResponse => $this->exportCsv()),
        ];
    }

    private function exportCsv(): StreamedResponse
    {
        $report = $this->report;

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, ['CoreMemory — sales & profit']);
            fputcsv($handle, ['Period', $report->from->format('Y-m-d'), $report->to->format('Y-m-d')]);
            fputcsv($handle, ['Basis', $report->basisLabel()]);
            fputcsv($handle, []);

            fputcsv($handle, ['Metric', 'Value (RM)']);
            foreach ([
                'Collected' => $report->collected()->toRinggit(),
                'Invoiced' => $report->invoiced()->toRinggit(),
                'Direct costs' => $report->costs()->toRinggit(),
                'Gross profit' => $report->grossProfit()->toRinggit(),
                'Gross margin %' => $report->grossMarginPercent(),
                'Average booking' => $report->averageBookingValue()->toRinggit(),
                'Conversion %' => $report->conversionRate(),
                'Outstanding' => $report->receivables()['total']->toRinggit(),
            ] as $label => $value) {
                fputcsv($handle, [$label, $value]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Package', 'Bookings', 'Revenue (RM)']);
            foreach ($report->revenueByPackage() as $row) {
                fputcsv($handle, [$row->name, $row->bookings, $row->revenue->toRinggit()]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Add-on', 'Taken', 'Attach rate %', 'Revenue (RM)']);
            foreach ($report->addOnAttachRate() as $row) {
                fputcsv($handle, [$row->name, $row->taken, $row->attach_rate, $row->revenue->toRinggit()]);
            }

            fclose($handle);
        }, 'corememory-finance-'.$report->from->format('Ymd').'-'.$report->to->format('Ymd').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

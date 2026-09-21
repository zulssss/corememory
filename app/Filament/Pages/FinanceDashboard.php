<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Invoice;
use App\Services\FinanceReport;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
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
 * Owner-only: staff work bookings, not the books.
 */
class FinanceDashboard extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.finance-dashboard';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Sales & profit';

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Sales & profit';

    /** @var array<string, mixed> */
    public array $filters = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
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
                Section::make()
                    ->columns(3)
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
        $today = CarbonImmutable::today();

        [$from, $to] = match ($preset) {
            'last_month' => [$today->subMonth()->startOfMonth(), $today->subMonth()->endOfMonth()],
            'this_year' => [$today->startOfYear(), $today->endOfYear()],
            'last_12_months' => [$today->subMonths(11)->startOfMonth(), $today->endOfMonth()],
            default => [$today->startOfMonth(), $today->endOfMonth()],
        };

        $this->filters['from'] = $from->toDateString();
        $this->filters['to'] = $to->toDateString();
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

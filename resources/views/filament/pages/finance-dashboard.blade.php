{{--
    The admin home: sales & profit.

    Every money figure obeys the one filter at the top, and revenue always
    carries its basis, so "RM 12,400" is never ambiguous about whether it is
    money received or money invoiced. The "needs attention" strip is the one
    exception: a request waiting for a reply is waiting whatever month is
    selected.

    Chart colours are Filament's own primary (amber) and info (blue) — checked
    for colour-blind separation and contrast in light and dark — and every
    series is also named in a legend and a tooltip, never by colour alone.
--}}
@php
    use App\Filament\Resources\Bookings\BookingResource;
    use App\Filament\Resources\Invoices\InvoiceResource;
    use Carbon\CarbonImmutable;

    $report = $this->report;
    $previous = $this->previousReport;
    $receivables = $report->receivables();
    $funnel = $report->funnel();
    $series = $report->monthlySeries();
    $attention = $this->attention;
    $canOpenInvoices = InvoiceResource::canAccess();

    // "RM 12.4k" on axes and sparklines; full figures stay in cards and tables.
    $short = function (float $value): string {
        $abs = abs($value);
        $text = $abs >= 1000 ? rtrim(rtrim(number_format($abs / 1000, 1), '0'), '.').'k' : number_format($abs);

        return ($value < 0 ? '−' : '').'RM '.$text;
    };
@endphp

<x-filament-panels::page>

    {{-- ---------------- Filter: presets + dates + basis, one card ---------------- --}}
    <div class="flex flex-col gap-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="inline-flex flex-wrap gap-1 self-start rounded-lg bg-gray-100 p-1 dark:bg-white/5" role="group" aria-label="{{ __('finance.period.label') }}">
            @foreach (\App\Filament\Pages\FinanceDashboard::PRESETS as $preset)
                @php $active = $this->activePreset === $preset; @endphp
                <button type="button" wire:click="applyPreset('{{ $preset }}')"
                        aria-pressed="{{ $active ? 'true' : 'false' }}"
                        @class([
                            'rounded-md px-3 py-1.5 text-xs font-semibold transition',
                            'bg-white text-gray-950 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-800 dark:text-white dark:ring-white/10' => $active,
                            'text-gray-600 hover:text-gray-950 dark:text-gray-400 dark:hover:text-white' => ! $active,
                        ])>
                    {{ __('finance.period.'.$preset) }}
                </button>
            @endforeach
        </div>

        {{ $this->filtersForm }}
    </div>

    {{-- ---------------- Needs attention ---------------- --}}
    @php
        $attentionCards = [
            [
                'value' => $attention['pending'],
                'label' => __('finance.attention.pending'),
                'action' => __('finance.attention.pending_action'),
                'url' => BookingResource::getUrl('index', ['filters' => ['status' => ['values' => ['pending']]]]),
                'icon' => 'heroicon-o-inbox-arrow-down',
                'urgent' => $attention['pending'] > 0,
            ],
            [
                'value' => $attention['upcoming'],
                'label' => __('finance.attention.upcoming'),
                'action' => __('finance.attention.upcoming_action'),
                'url' => '#upcoming',
                'icon' => 'heroicon-o-calendar-days',
                'urgent' => false,
            ],
            [
                'value' => $attention['overdue_count'],
                'label' => __('finance.attention.overdue'),
                'sub' => $attention['overdue_count'] > 0 ? __('finance.attention.overdue_total', ['total' => $attention['overdue_total']]) : null,
                'action' => __('finance.attention.overdue_action'),
                // Staff see the dashboard but not invoices: the figure, no link.
                'url' => $canOpenInvoices ? InvoiceResource::getUrl('index') : null,
                'icon' => 'heroicon-o-exclamation-triangle',
                'urgent' => $attention['overdue_count'] > 0,
            ],
        ];
    @endphp

    <section aria-labelledby="attention-heading" class="flex flex-col gap-3">
        <h2 id="attention-heading" class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('finance.attention.heading') }}</h2>

        <div class="grid gap-4 md:grid-cols-3">
            @foreach ($attentionCards as $card)
                <div @class([
                    'flex items-start gap-4 rounded-xl bg-white p-4 shadow-sm ring-1 dark:bg-gray-900',
                    'ring-warning-600/30 dark:ring-warning-400/30' => $card['urgent'],
                    'ring-gray-950/5 dark:ring-white/10' => ! $card['urgent'],
                ])>
                    <span @class([
                        'flex h-10 w-10 shrink-0 items-center justify-center rounded-lg',
                        'bg-warning-50 text-warning-600 dark:bg-warning-400/10 dark:text-warning-400' => $card['urgent'],
                        'bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400' => ! $card['urgent'],
                    ])>
                        <x-filament::icon :icon="$card['icon']" class="h-5 w-5" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="text-2xl font-bold tabular-nums leading-none text-gray-950 dark:text-white">{{ $card['value'] }}</div>
                        <div class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $card['label'] }}</div>
                        @if ($card['sub'] ?? null)
                            <div class="mt-0.5 text-xs font-medium text-gray-950 dark:text-white">{{ $card['sub'] }}</div>
                        @endif
                        @if ($card['url'] && $card['value'] > 0)
                            <a href="{{ $card['url'] }}" class="mt-2 inline-block text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">
                                {{ $card['action'] }} →
                            </a>
                        @elseif ($card['value'] === 0)
                            <div class="mt-2 text-xs text-gray-400 dark:text-gray-500">{{ __('finance.attention.clear') }}</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ---------------- Headline cards ---------------- --}}
    @php
        $cards = [
            [
                'label' => __('finance.stats.revenue'),
                'value' => $report->revenue()->formatCompact(),
                'sub' => $report->basisLabel(),
                'change' => $this->change($report->revenue()->cents, $previous->revenue()->cents),
                'good' => 'up',
                'spark' => $series['revenue'],
                'colour' => 'text-primary-600',
            ],
            [
                'label' => __('finance.stats.costs'),
                'value' => $report->costs()->formatCompact(),
                'sub' => __('finance.stats.costs_caption'),
                'change' => $this->change($report->costs()->cents, $previous->costs()->cents),
                'good' => 'down',
                'spark' => $series['costs'],
                'colour' => 'text-info-600 dark:text-info-500',
            ],
            [
                'label' => __('finance.stats.gross_profit'),
                'value' => $report->grossProfit()->formatCompact(),
                'sub' => $report->grossMarginPercent() === null
                    ? __('finance.stats.no_revenue')
                    : $report->grossMarginPercent().'% '.__('finance.stats.margin'),
                'change' => $this->change($report->grossProfit()->cents, $previous->grossProfit()->cents),
                'good' => 'up',
                'spark' => $series['profit'],
                'colour' => 'text-gray-900 dark:text-gray-100',
            ],
            [
                'label' => __('finance.stats.receivables'),
                'value' => $receivables['total']->formatCompact(),
                'sub' => trans_choice('{0}Nothing outstanding|{1}:count invoice|[2,*]:count invoices', $receivables['count'], ['count' => $receivables['count']]),
                'change' => null,
                'good' => 'down',
                'spark' => null,
                'colour' => null,
            ],
        ];
    @endphp

    {{-- Each card is label → figure → context, top to bottom, so the eye lands
         on the number first. The change is a pill: a different KIND of number
         from the ringgit figure, so it must not read as part of the caption. --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($cards as $card)
            <div class="flex flex-col gap-3 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-start justify-between gap-3">
                    <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        {{ $card['label'] }}
                    </div>

                    {{-- Twelve months at a glance. Decorative: the chart below
                         carries the same numbers with axes and tooltips. --}}
                    @if ($card['spark'] && max(array_map('abs', $card['spark'])) > 0)
                        @php
                            $sMax = max($card['spark']); $sMin = min(0, min($card['spark']));
                            $sRange = max(1, $sMax - $sMin);
                            $sPoints = collect($card['spark'])->map(fn ($v, $i) => round($i * (80 / 11), 1).','.round(22 - (($v - $sMin) / $sRange) * 20, 1))->implode(' ');
                        @endphp
                        <svg viewBox="0 0 80 24" class="h-6 w-20 shrink-0 {{ $card['colour'] }}" aria-hidden="true">
                            <polyline points="{{ $sPoints }}" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" />
                        </svg>
                    @endif
                </div>

                <div class="text-3xl font-bold tabular-nums leading-none tracking-tight text-gray-950 dark:text-white">
                    {{ $card['value'] }}
                </div>

                <div class="mt-auto flex flex-wrap items-center gap-2">
                    @if ($card['change'] !== null)
                        @php
                            $rising = $card['change'] > 0;
                            $isGood = $card['good'] === 'up' ? $rising : ! $rising;
                            // Against a near-empty previous period changes like
                            // 797.6% read as noise and crowd out the caption.
                            $magnitude = abs($card['change']);
                            $display = $magnitude >= 1000 ? '999+' : rtrim(rtrim(number_format($magnitude, 1), '0'), '.');
                        @endphp
                        <span @class([
                            'inline-flex items-center gap-0.5 rounded-md px-1.5 py-0.5 text-xs font-semibold ring-1 ring-inset',
                            'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/30' => $isGood,
                            'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-400 dark:ring-danger-400/30' => ! $isGood,
                        ]) title="{{ __('finance.period.vs_previous') }}">
                            {{ $rising ? '↑' : '↓' }} {{ $display }}%
                        </span>
                    @endif

                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $card['sub'] }}</span>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ---------------- Monthly chart ---------------- --}}
    @php
        // One RM axis for all three series — they share a unit, so no second
        // scale. It reaches below zero only when a month made a loss.
        $all = [...$series['revenue'], ...$series['costs'], ...$series['profit']];
        $rawMax = max(1, max($all));
        $rawMin = min(0, min($all));

        // A "nice" step (1, 2 or 5 × 10^n) giving about four gridlines.
        $span = $rawMax - $rawMin;
        $magnitude = 10 ** floor(log10($span / 4));
        $niceStep = collect([1, 2, 5, 10])->map(fn ($m) => $m * $magnitude)->first(fn ($s) => $span / $s <= 5);
        $yMax = ceil($rawMax / $niceStep) * $niceStep;
        $yMin = floor($rawMin / $niceStep) * $niceStep;

        $w = 800; $h = 280; $left = 64; $right = 16; $top = 16; $bottom = 32;
        $plotW = $w - $left - $right;
        $plotH = $h - $top - $bottom;
        $n = count($series['labels']);
        $slot = $plotW / max(1, $n);
        $barW = min(16, $slot * 0.28);
        $y = fn (float $v): float => $top + ($yMax - $v) / max(1, $yMax - $yMin) * $plotH;
        $zero = $y(0);
        $centre = fn (int $i): float => $left + $slot * $i + $slot / 2;

        // A bar with a 4px rounded end away from the baseline, square at it.
        $bar = function (float $x, float $value) use ($y, $zero, $barW): string {
            $end = $y($value);
            $height = abs($zero - $end);
            if ($height < 0.5) {
                return '';
            }
            $r = min(4, $height, $barW / 2);
            $up = $value >= 0;
            $tip = $end;
            $base = $zero;
            $d = $up
                ? "M{$x},{$base} V".($tip + $r)." Q{$x},{$tip} ".($x + $r).",{$tip} H".($x + $barW - $r)." Q".($x + $barW).",{$tip} ".($x + $barW).','.($tip + $r)." V{$base} Z"
                : "M{$x},{$base} V".($tip - $r)." Q{$x},{$tip} ".($x + $r).",{$tip} H".($x + $barW - $r)." Q".($x + $barW).",{$tip} ".($x + $barW).','.($tip - $r)." V{$base} Z";

            return $d;
        };

        $months = collect(range(0, $n - 1))->map(fn ($i) => CarbonImmutable::today()->subMonths($n - 1 - $i)->startOfMonth());
        $inPeriod = $months->map(fn ($m) => $m->endOfMonth()->gte($report->from) && $m->lte($report->to))->all();

        $tooltip = collect(range(0, $n - 1))->map(fn ($i) => [
            'month' => $series['labels'][$i],
            'revenue' => $short($series['revenue'][$i]),
            'costs' => $short($series['costs'][$i]),
            'profit' => $short($series['profit'][$i]),
            'left' => round($centre($i) / $w * 100, 2),
        ])->all();
    @endphp

    <x-filament::section>
        <x-slot name="heading">{{ __('finance.widgets.trend') }}</x-slot>
        <x-slot name="description">
            {{ __('finance.widgets.trend_description', ['basis' => $report->basisLabel()]) }} · {{ __('finance.chart.in_period') }}
        </x-slot>

        {{-- Legend first: identity never rests on colour alone. --}}
        <div class="mb-3 flex flex-wrap gap-x-5 gap-y-1 text-xs text-gray-600 dark:text-gray-400">
            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-primary-600"></span>{{ __('finance.chart.revenue') }}</span>
            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-info-600 dark:bg-info-500"></span>{{ __('finance.chart.costs') }}</span>
            <span class="flex items-center gap-1.5"><span class="h-0.5 w-4 bg-gray-900 dark:bg-gray-100"></span>{{ __('finance.chart.profit') }}</span>
        </div>

        <div class="overflow-x-auto">
            <div class="relative min-w-[40rem]" x-data="{ i: null, points: @js($tooltip) }" x-on:mouseleave="i = null">
                <svg viewBox="0 0 {{ $w }} {{ $h }}" class="h-72 w-full" role="img"
                     aria-label="{{ __('finance.widgets.trend') }}">

                    {{-- Months inside the selected period, shaded. --}}
                    @foreach ($inPeriod as $i => $inside)
                        @if ($inside)
                            <rect x="{{ $left + $slot * $i }}" y="{{ $top }}" width="{{ $slot }}" height="{{ $plotH }}"
                                  class="fill-gray-100 dark:fill-white/5" />
                        @endif
                    @endforeach

                    {{-- Hover band --}}
                    <rect x-cloak x-show="i !== null" :x="{{ $left }} + {{ $slot }} * (i ?? 0)" y="{{ $top }}" width="{{ $slot }}" height="{{ $plotH }}"
                          class="fill-gray-200/70 dark:fill-white/10" />

                    {{-- Gridlines and RM labels: recessive, so the data leads. --}}
                    @for ($tick = $yMin; $tick <= $yMax + 0.001; $tick += $niceStep)
                        <line x1="{{ $left }}" x2="{{ $w - $right }}" y1="{{ $y($tick) }}" y2="{{ $y($tick) }}"
                              stroke="currentColor" @class(['text-gray-200 dark:text-white/10']) stroke-width="1"
                              @if (abs($tick) < 0.001) stroke-opacity="1" @else stroke-dasharray="2 4" @endif />
                        <text x="{{ $left - 8 }}" y="{{ $y($tick) + 3 }}" text-anchor="end" font-size="10"
                              class="fill-gray-500 dark:fill-gray-400">{{ $short($tick) }}</text>
                    @endfor

                    {{-- Bars: revenue then costs, 2px apart. --}}
                    @foreach ($series['labels'] as $i => $label)
                        @php $x0 = $centre($i) - $barW - 1; @endphp
                        <path d="{{ $bar($x0, $series['revenue'][$i]) }}" class="fill-primary-600" />
                        <path d="{{ $bar($x0 + $barW + 2, $series['costs'][$i]) }}" class="fill-info-600 dark:fill-info-500" />
                        <text x="{{ $centre($i) }}" y="{{ $h - 10 }}" text-anchor="middle" font-size="10"
                              @class(['fill-gray-900 font-semibold dark:fill-gray-100' => $inPeriod[$i], 'fill-gray-500 dark:fill-gray-400' => ! $inPeriod[$i]])>{{ \Illuminate\Support\Str::before($label, ' ') }}</text>
                    @endforeach

                    {{-- Profit: a 2px line with ringed markers. --}}
                    <polyline fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"
                              class="text-gray-900 dark:text-gray-100"
                              points="{{ collect($series['profit'])->map(fn ($v, $i) => round($centre($i), 1).','.round($y($v), 1))->implode(' ') }}" />
                    @foreach ($series['profit'] as $i => $v)
                        <circle cx="{{ round($centre($i), 1) }}" cy="{{ round($y($v), 1) }}" r="4" stroke-width="2"
                                class="fill-gray-900 stroke-white dark:fill-gray-100 dark:stroke-gray-900" />
                    @endforeach

                    {{-- Hit targets: the whole month column, wider than any mark. --}}
                    @foreach ($series['labels'] as $i => $label)
                        <rect x="{{ $left + $slot * $i }}" y="{{ $top }}" width="{{ $slot }}" height="{{ $plotH }}"
                              fill="transparent" x-on:mouseenter="i = {{ $i }}">
                            <title>{{ $label }} — {{ __('finance.chart.revenue') }} {{ $short($series['revenue'][$i]) }}, {{ __('finance.chart.costs') }} {{ $short($series['costs'][$i]) }}, {{ __('finance.chart.profit') }} {{ $short($series['profit'][$i]) }}</title>
                        </rect>
                    @endforeach
                </svg>

                {{-- Tooltip: values in ink, a colour key beside each. --}}
                <div x-cloak x-show="i !== null" x-transition.opacity
                     class="pointer-events-none absolute top-2 z-10 w-44 -translate-x-1/2 rounded-lg bg-white p-3 text-xs shadow-lg ring-1 ring-gray-950/10 dark:bg-gray-800 dark:ring-white/10"
                     :style="i !== null && `left: min(max(${points[i].left}%, 6rem), calc(100% - 6rem))`">
                    <template x-if="i !== null">
                        <div class="flex flex-col gap-1.5">
                            <div class="font-semibold text-gray-950 dark:text-white" x-text="points[i].month"></div>
                            <div class="flex items-center justify-between gap-3 text-gray-600 dark:text-gray-300">
                                <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm bg-primary-600"></span>{{ __('finance.chart.revenue') }}</span>
                                <span class="font-semibold tabular-nums text-gray-950 dark:text-white" x-text="points[i].revenue"></span>
                            </div>
                            <div class="flex items-center justify-between gap-3 text-gray-600 dark:text-gray-300">
                                <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm bg-info-600 dark:bg-info-500"></span>{{ __('finance.chart.costs') }}</span>
                                <span class="font-semibold tabular-nums text-gray-950 dark:text-white" x-text="points[i].costs"></span>
                            </div>
                            <div class="flex items-center justify-between gap-3 border-t border-gray-100 pt-1.5 text-gray-600 dark:border-white/10 dark:text-gray-300">
                                <span class="flex items-center gap-1.5"><span class="h-0.5 w-3 bg-gray-900 dark:bg-gray-100"></span>{{ __('finance.chart.profit') }}</span>
                                <span class="font-semibold tabular-nums text-gray-950 dark:text-white" x-text="points[i].profit"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </x-filament::section>

    {{-- items-start: without it every section in a row stretches to the tallest
         one, dragging empty white space under the shorter. --}}
    <div class="grid items-start gap-6 lg:grid-cols-2">

        {{-- ---------------- Upcoming events ---------------- --}}
        <div id="upcoming" class="scroll-mt-24">
            <x-filament::section>
                <x-slot name="heading">{{ __('finance.widgets.upcoming') }}</x-slot>
                <x-slot name="description">{{ __('finance.widgets.upcoming_description') }}</x-slot>

                @if ($this->upcoming->isEmpty())
                    <p class="text-sm text-gray-500">Nothing in the next 30 days.</p>
                @else
                    <div class="flex flex-col divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($this->upcoming as $booking)
                            @php $first = $booking->primaryDate(); @endphp
                            <a href="{{ BookingResource::getUrl('edit', ['record' => $booking]) }}"
                               class="group flex items-center gap-4 py-2.5 text-sm">
                                {{-- A calendar leaf: the date is what you scan for. --}}
                                <span class="flex w-11 shrink-0 flex-col items-center rounded-md bg-gray-100 py-1 dark:bg-white/5">
                                    <span class="text-[10px] font-semibold uppercase text-gray-500 dark:text-gray-400">{{ $first?->event_date->translatedFormat('M') }}</span>
                                    <span class="text-base font-bold leading-none tabular-nums text-gray-950 dark:text-white">{{ $first?->event_date->format('j') }}</span>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium text-gray-950 group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400">
                                        {{ $booking->coupleNames() }}
                                    </span>
                                    <span class="block truncate text-xs text-gray-500 dark:text-gray-400">
                                        {{ $booking->dates->where('event_date', $first?->event_date)->map(fn ($d) => $d->session_slot->label())->implode(' + ') }}
                                        · {{ $booking->package?->name }}
                                    </span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>
        </div>

        {{-- ---------------- Funnel ---------------- --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('finance.widgets.funnel') }}</x-slot>
            <x-slot name="description">
                @if ($report->conversionRate() !== null)
                    {{ __('finance.stats.conversion') }}: <strong>{{ $report->conversionRate() }}%</strong> ·
                @endif
                {{ $report->averageBookingValue()->formatCompact() }} {{ __('finance.stats.avg_booking_caption') }}
            </x-slot>

            @php $topCount = max(1, $funnel['enquiries']); @endphp

            <div class="flex flex-col gap-4">
                @foreach ($funnel as $stage => $count)
                    @php $share = round($count / $topCount * 100, 1); @endphp
                    <div>
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <span class="font-medium text-gray-700 dark:text-gray-300">{{ __('finance.funnel.'.$stage) }}</span>
                            <span class="flex items-baseline gap-2">
                                {{-- Share of the top of the funnel, so a stage reads
                                     without doing the division in your head. --}}
                                <span class="text-xs tabular-nums text-gray-500 dark:text-gray-400">{{ $share }}%</span>
                                <span class="text-base font-bold tabular-nums text-gray-950 dark:text-white">{{ $count }}</span>
                            </span>
                        </div>
                        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                            <div class="h-full rounded-full bg-primary-600" style="width: {{ max($share, 1) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        {{-- ---------------- Revenue by package ---------------- --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('finance.widgets.top_packages') }}</x-slot>

            @if ($report->revenueByPackage()->isEmpty())
                <p class="text-sm text-gray-500">{{ __('finance.empty') }}</p>
            @else
                @php $topPackage = max(1, $report->revenueByPackage()->max(fn ($r) => $r->revenue->cents)); @endphp
                <div class="flex flex-col gap-3">
                    @foreach ($report->revenueByPackage() as $row)
                        <div>
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <span class="min-w-0 truncate font-medium text-gray-700 dark:text-gray-300">{{ $row->name }}</span>
                                <span class="flex shrink-0 items-baseline gap-2">
                                    <span class="text-xs tabular-nums text-gray-500 dark:text-gray-400">{{ trans_choice('{1}:count booking|[2,*]:count bookings', $row->bookings, ['count' => $row->bookings]) }}</span>
                                    <span class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ $row->revenue->formatCompact() }}</span>
                                </span>
                            </div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                <div class="h-full rounded-full bg-primary-600" style="width: {{ max(1, round($row->revenue->cents / $topPackage * 100, 1)) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        {{-- ---------------- Add-on attach rate ---------------- --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('finance.widgets.add_ons') }}</x-slot>

            @if ($report->addOnAttachRate()->isEmpty())
                <p class="text-sm text-gray-500">{{ __('finance.empty') }}</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs uppercase tracking-wider text-gray-500 dark:border-white/10 dark:text-gray-400">
                            <th class="pb-2 text-left font-semibold">Add-on</th>
                            <th class="pb-2 pl-3 text-right font-semibold">Taken</th>
                            <th class="pb-2 pl-3 text-right font-semibold">Attach</th>
                            <th class="pb-2 pl-3 text-right font-semibold">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($report->addOnAttachRate() as $row)
                            <tr>
                                <td class="py-2.5 pr-3 font-medium text-gray-700 dark:text-gray-300">{{ $row->name }}</td>
                                <td class="py-2.5 pl-3 text-right tabular-nums text-gray-500 dark:text-gray-400">{{ $row->taken }}</td>
                                <td class="py-2.5 pl-3 text-right tabular-nums text-gray-500 dark:text-gray-400">{{ $row->attach_rate }}%</td>
                                <td class="py-2.5 pl-3 text-right font-semibold tabular-nums text-gray-950 dark:text-white">{{ $row->revenue->formatCompact() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-filament::section>

        {{-- ---------------- Receivables ageing ---------------- --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('finance.widgets.ageing') }}</x-slot>
            <x-slot name="description">{{ $receivables['total']->formatCompact() }} outstanding in total</x-slot>

            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach (['not_due', '0-30', '31-60', '60+'] as $bucket)
                        @php $amount = $receivables['buckets'][$bucket]; @endphp
                        <tr>
                            <td class="py-2.5 pr-4 text-gray-700 dark:text-gray-300">{{ __('finance.ageing.'.$bucket) }}</td>
                            <td @class([
                                'py-2.5 text-right font-semibold tabular-nums',
                                // Only the oldest bucket earns alarm colour, and only
                                // when there is something in it.
                                'text-danger-600 dark:text-danger-400' => $bucket === '60+' && ! $amount->isZero(),
                                'text-gray-400 dark:text-gray-600' => $amount->isZero(),
                                'text-gray-950 dark:text-white' => $bucket !== '60+' && ! $amount->isZero(),
                            ])>
                                {{ $amount->formatCompact() }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>

        {{-- ---------------- Overdue invoices ---------------- --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('finance.widgets.overdue') }}</x-slot>

            @if ($this->overdueInvoices->isEmpty())
                <p class="text-sm text-gray-500">Nothing overdue.</p>
            @else
                <div class="flex flex-col divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($this->overdueInvoices as $invoice)
                        {{-- Staff see the list but cannot open invoices. --}}
                        <{{ $canOpenInvoices ? 'a' : 'div' }}
                            @if ($canOpenInvoices) href="{{ InvoiceResource::getUrl('edit', ['record' => $invoice]) }}" @endif
                            class="group flex items-center justify-between gap-3 py-2.5 text-sm">
                            <span class="min-w-0">
                                <span class="block truncate font-medium tabular-nums text-gray-950 dark:text-white {{ $canOpenInvoices ? 'group-hover:text-primary-600 dark:group-hover:text-primary-400' : '' }}">
                                    {{ $invoice->number }}
                                </span>
                                <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ $invoice->client_name }}</span>
                            </span>
                            <span class="shrink-0 text-right">
                                <span class="block font-semibold tabular-nums text-danger-600 dark:text-danger-400">{{ $invoice->outstanding()->formatCompact() }}</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $invoice->daysOverdue() }} days overdue</span>
                            </span>
                        </{{ $canOpenInvoices ? 'a' : 'div' }}>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        {{-- ---------------- Where enquiries come from ---------------- --}}
        @php
            $sources = collect($report->sourceBreakdown())->sortDesc();
            $sourceTotal = max(1, $sources->sum());
        @endphp
        <x-filament::section>
            <x-slot name="heading">{{ __('finance.widgets.sources') }}</x-slot>

            @if ($sources->isEmpty())
                <p class="text-sm text-gray-500">{{ __('finance.empty') }}</p>
            @else
                <div class="flex flex-col gap-3">
                    @foreach ($sources as $source => $count)
                        @php $label = \App\Enums\EnquirySource::tryFrom((string) $source)?->label() ?? ucfirst((string) $source); @endphp
                        <div>
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <span class="font-medium text-gray-700 dark:text-gray-300">{{ $label }}</span>
                                <span class="flex items-baseline gap-2">
                                    <span class="text-xs tabular-nums text-gray-500 dark:text-gray-400">{{ round($count / $sourceTotal * 100) }}%</span>
                                    <span class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ $count }}</span>
                                </span>
                            </div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                <div class="h-full rounded-full bg-primary-600" style="width: {{ max(1, round($count / $sourceTotal * 100, 1)) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    </div>

</x-filament-panels::page>

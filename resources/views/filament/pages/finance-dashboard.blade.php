{{--
    Sales & profit.

    Every figure obeys the one filter at the top. Revenue figures always carry
    the basis next to them, so "RM 12,400" is never ambiguous about whether it
    is money received or money invoiced.
--}}
@php
    $report = $this->report;
    $previous = $this->previousReport;
    $receivables = $report->receivables();
    $funnel = $report->funnel();
    $series = $report->monthlySeries();
@endphp

<x-filament-panels::page>

    {{-- ---------------- Filter ---------------- --}}
    {{ $this->filtersForm }}

    <div class="flex flex-wrap gap-2">
        @foreach ([
            'this_month' => __('finance.period.this_month'),
            'last_month' => __('finance.period.last_month'),
            'this_year' => __('finance.period.this_year'),
            'last_12_months' => __('finance.period.last_12_months'),
        ] as $preset => $label)
            <x-filament::button wire:click="applyPreset('{{ $preset }}')" color="gray" size="xs">
                {{ $label }}
            </x-filament::button>
        @endforeach
    </div>

    {{-- ---------------- Headline stats ---------------- --}}
    @php
        $cards = [
            [
                'label' => __('finance.stats.revenue'),
                'value' => $report->revenue()->formatCompact(),
                'sub' => $report->basisLabel(),
                'change' => $this->change($report->revenue()->cents, $previous->revenue()->cents),
                'good' => 'up',
            ],
            [
                'label' => __('finance.stats.costs'),
                'value' => $report->costs()->formatCompact(),
                'sub' => __('finance.widgets.upcoming_description'),
                'change' => $this->change($report->costs()->cents, $previous->costs()->cents),
                'good' => 'down',
            ],
            [
                'label' => __('finance.stats.gross_profit'),
                'value' => $report->grossProfit()->formatCompact(),
                'sub' => $report->grossMarginPercent() === null
                    ? __('finance.stats.no_revenue')
                    : $report->grossMarginPercent().'% '.__('finance.stats.margin'),
                'change' => $this->change($report->grossProfit()->cents, $previous->grossProfit()->cents),
                'good' => 'up',
            ],
            [
                'label' => __('finance.stats.receivables'),
                'value' => $receivables['total']->formatCompact(),
                'sub' => trans_choice('{0}Nothing outstanding|{1}:count invoice|[2,*]:count invoices', $receivables['count'], ['count' => $receivables['count']]),
                'change' => null,
                'good' => 'down',
            ],
        ];
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($cards as $card)
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    {{ $card['label'] }}
                </div>

                <div class="mt-1 text-2xl font-semibold tabular-nums text-gray-950 dark:text-white">
                    {{ $card['value'] }}
                </div>

                <div class="mt-1 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                    <span>{{ $card['sub'] }}</span>

                    @if ($card['change'] !== null)
                        @php
                            $rising = $card['change'] > 0;
                            $isGood = $card['good'] === 'up' ? $rising : ! $rising;
                        @endphp
                        <span class="font-medium {{ $isGood ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                            {{ $rising ? '↑' : '↓' }} {{ abs($card['change']) }}%
                        </span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- ---------------- Revenue vs cost vs profit ---------------- --}}
    <x-filament::section>
        <x-slot name="heading">{{ __('finance.widgets.trend') }}</x-slot>
        <x-slot name="description">
            {{ __('finance.widgets.trend_description', ['basis' => $report->basisLabel()]) }}
        </x-slot>

        {{-- A plain inline SVG rather than a charting library: three series
             over twelve points needs no dependency, and it prints. --}}
        @php
            $max = max(1, max([...$series['revenue'], ...$series['costs'], 1]));
            $w = 760; $h = 220; $pad = 28;
            $step = count($series['labels']) > 1 ? ($w - $pad * 2) / (count($series['labels']) - 1) : 0;
            $y = fn ($v) => $h - $pad - ($v / $max) * ($h - $pad * 2);
            $path = function (array $vals) use ($step, $pad, $y) {
                return collect($vals)->map(fn ($v, $i) => ($i === 0 ? 'M' : 'L')
                    .round($pad + $i * $step, 1).' '.round($y($v), 1))->implode(' ');
            };
        @endphp

        <div class="overflow-x-auto">
            <svg viewBox="0 0 {{ $w }} {{ $h }}" class="h-56 w-full min-w-[40rem]" role="img"
                 aria-label="{{ __('finance.widgets.trend') }}">
                {{-- baseline --}}
                <line x1="{{ $pad }}" y1="{{ $h - $pad }}" x2="{{ $w - $pad }}" y2="{{ $h - $pad }}"
                      stroke="currentColor" stroke-opacity="0.15" />

                <path d="{{ $path($series['revenue']) }}" fill="none" stroke="#3f6b4f" stroke-width="2" />
                <path d="{{ $path($series['costs']) }}" fill="none" stroke="#8f3a32" stroke-width="2" />
                <path d="{{ $path($series['profit']) }}" fill="none" stroke="#8c4b2f" stroke-width="2"
                      stroke-dasharray="4 3" />

                @foreach ($series['labels'] as $i => $label)
                    @if ($i % 2 === 0)
                        <text x="{{ round($pad + $i * $step, 1) }}" y="{{ $h - 8 }}"
                              font-size="9" text-anchor="middle" fill="currentColor" fill-opacity="0.5">
                            {{ $label }}
                        </text>
                    @endif
                @endforeach
            </svg>
        </div>

        <div class="mt-2 flex flex-wrap gap-4 text-xs text-gray-600 dark:text-gray-400">
            @foreach ([
                ['Revenue', '#3f6b4f'],
                ['Direct costs', '#8f3a32'],
                ['Gross profit', '#8c4b2f'],
            ] as [$label, $colour])
                <span class="flex items-center gap-1.5">
                    <span class="h-0.5 w-4" style="background: {{ $colour }}"></span>{{ $label }}
                </span>
            @endforeach
        </div>
    </x-filament::section>

    <div class="grid gap-6 lg:grid-cols-2">

        {{-- ---------------- Funnel ---------------- --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('finance.widgets.funnel') }}</x-slot>
            <x-slot name="description">
                @if ($report->conversionRate() !== null)
                    {{ __('finance.stats.conversion') }}: <strong>{{ $report->conversionRate() }}%</strong>
                @endif
            </x-slot>

            @php $top = max(1, $funnel['enquiries']); @endphp

            <div class="flex flex-col gap-3">
                @foreach ($funnel as $stage => $count)
                    <div>
                        <div class="flex items-baseline justify-between text-sm">
                            <span class="text-gray-700 dark:text-gray-300">{{ __('finance.funnel.'.$stage) }}</span>
                            <span class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ $count }}</span>
                        </div>
                        <div class="mt-1 h-2 rounded-full bg-gray-100 dark:bg-gray-800">
                            <div class="h-2 rounded-full bg-primary-500"
                                 style="width: {{ round($count / $top * 100, 1) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        {{-- ---------------- Receivables ageing ---------------- --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('finance.widgets.ageing') }}</x-slot>
            <x-slot name="description">{{ $receivables['total']->formatCompact() }} outstanding in total</x-slot>

            <table class="w-full text-sm">
                <tbody>
                    @foreach (['not_due', '0-30', '31-60', '60+'] as $bucket)
                        <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                            <td class="py-2 text-gray-700 dark:text-gray-300">
                                {{ __('finance.ageing.'.$bucket) }}
                            </td>
                            <td class="py-2 text-right font-medium tabular-nums {{ $bucket === '60+' && ! $receivables['buckets'][$bucket]->isZero() ? 'text-danger-600 dark:text-danger-400' : 'text-gray-950 dark:text-white' }}">
                                {{ $receivables['buckets'][$bucket]->formatCompact() }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>

        {{-- ---------------- Revenue by package ---------------- --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('finance.widgets.top_packages') }}</x-slot>

            @if ($report->revenueByPackage()->isEmpty())
                <p class="text-sm text-gray-500">{{ __('finance.empty') }}</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs uppercase tracking-wide text-gray-500">
                            <th class="pb-2 text-left font-medium">Package</th>
                            <th class="pb-2 text-right font-medium">Bookings</th>
                            <th class="pb-2 text-right font-medium">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report->revenueByPackage() as $row)
                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                <td class="py-2 text-gray-700 dark:text-gray-300">{{ $row->name }}</td>
                                <td class="py-2 text-right tabular-nums">{{ $row->bookings }}</td>
                                <td class="py-2 text-right font-medium tabular-nums">{{ $row->revenue->formatCompact() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
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
                        <tr class="text-xs uppercase tracking-wide text-gray-500">
                            <th class="pb-2 text-left font-medium">Add-on</th>
                            <th class="pb-2 text-right font-medium">Taken</th>
                            <th class="pb-2 text-right font-medium">Attach</th>
                            <th class="pb-2 text-right font-medium">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report->addOnAttachRate() as $row)
                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                <td class="py-2 text-gray-700 dark:text-gray-300">{{ $row->name }}</td>
                                <td class="py-2 text-right tabular-nums">{{ $row->taken }}</td>
                                <td class="py-2 text-right tabular-nums">{{ $row->attach_rate }}%</td>
                                <td class="py-2 text-right font-medium tabular-nums">{{ $row->revenue->formatCompact() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-filament::section>

        {{-- ---------------- Upcoming events ---------------- --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('finance.widgets.upcoming') }}</x-slot>
            <x-slot name="description">{{ __('finance.widgets.upcoming_description') }}</x-slot>

            @if ($this->upcoming->isEmpty())
                <p class="text-sm text-gray-500">Nothing in the next 30 days.</p>
            @else
                <div class="flex flex-col divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($this->upcoming as $booking)
                        <a href="{{ \App\Filament\Resources\Bookings\BookingResource::getUrl('edit', ['record' => $booking]) }}"
                           class="flex items-baseline justify-between gap-3 py-2 text-sm hover:text-primary-600">
                            <span>
                                <span class="font-medium text-gray-950 dark:text-white">{{ $booking->coupleNames() }}</span>
                                <span class="block text-xs text-gray-500">{{ $booking->package?->name }}</span>
                            </span>
                            <span class="shrink-0 text-right text-xs text-gray-500">
                                {{ $booking->primaryDate()?->event_date->translatedFormat('d M Y') }}
                                <span class="block">{{ $booking->primaryDate()?->session_slot->label() }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        {{-- ---------------- Overdue invoices ---------------- --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('finance.widgets.overdue') }}</x-slot>

            @if ($this->overdueInvoices->isEmpty())
                <p class="text-sm text-gray-500">Nothing overdue. </p>
            @else
                <div class="flex flex-col divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($this->overdueInvoices as $invoice)
                        <a href="{{ \App\Filament\Resources\Invoices\InvoiceResource::getUrl('edit', ['record' => $invoice]) }}"
                           class="flex items-baseline justify-between gap-3 py-2 text-sm hover:text-primary-600">
                            <span>
                                <span class="font-medium text-gray-950 dark:text-white">{{ $invoice->number }}</span>
                                <span class="block text-xs text-gray-500">{{ $invoice->client_name }}</span>
                            </span>
                            <span class="shrink-0 text-right">
                                <span class="font-medium tabular-nums text-danger-600 dark:text-danger-400">
                                    {{ $invoice->outstanding()->formatCompact() }}
                                </span>
                                <span class="block text-xs text-gray-500">{{ $invoice->daysOverdue() }} days</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    </div>

</x-filament-panels::page>

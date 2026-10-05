{{--
    Month view of what the studio actually has on.

    Design intent: a studio owner scans this to answer "are we free on the
    14th?" — so a FREE slot must read as quiet empty space and a TAKEN one must
    read as a solid block. Colour alone never carries the meaning: every slot
    also shows its initial (M/A/E) and, when taken, the couple's name.
--}}
@php
    $slotNames = ['morning' => 'Morning', 'afternoon' => 'Afternoon', 'evening' => 'Evening'];
@endphp

<x-filament-panels::page>

    {{-- ---------------- Month navigation ---------------- --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                {{ $this->monthStart()->translatedFormat('F Y') }}
            </h2>
            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                Three sessions a day. One crew, so a session is exclusive.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <x-filament::button wire:click="previousMonth" color="gray" size="sm" icon="heroicon-m-chevron-left">
                Previous
            </x-filament::button>
            <x-filament::button wire:click="today" color="gray" size="sm">Today</x-filament::button>
            <x-filament::button wire:click="nextMonth" color="gray" size="sm" icon="heroicon-m-chevron-right" icon-position="after">
                Next
            </x-filament::button>
        </div>
    </div>

    <x-filament::section>
        {{-- ---------------- Legend ---------------- --}}
        <div class="mb-4 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs font-medium text-gray-600 dark:text-gray-400">
            @foreach ([
                ['Confirmed', 'bg-success-500'],
                ['Enquiry pending', 'bg-warning-500'],
                ['Blocked', 'bg-danger-500'],
                ['Free', 'bg-gray-200 dark:bg-gray-700'],
            ] as [$label, $colour])
                <span class="flex items-center gap-1.5">
                    <span class="size-2.5 shrink-0 rounded-full {{ $colour }}"></span>{{ $label }}
                </span>
            @endforeach
        </div>

        {{-- ---------------- Grid ---------------- --}}
        <div class="overflow-x-auto">
            <div class="min-w-[52rem] overflow-hidden rounded-xl ring-1 ring-gray-950/10 dark:ring-white/10">

                {{-- Weekday header --}}
                <div class="grid grid-cols-7 border-b border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">
                    @foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $i => $dayName)
                        <div @class([
                            'px-2 py-2 text-center text-xs font-semibold uppercase tracking-wider',
                            'text-gray-500 dark:text-gray-400' => $i < 5,
                            // Saturday is the wedding day in this market — worth
                            // being able to find at a glance.
                            'text-primary-600 dark:text-primary-400' => $i >= 5,
                        ])>
                            {{ $dayName }}
                        </div>
                    @endforeach
                </div>

                {{-- Days. The 1px separators come from a gap over a tinted
                     background rather than per-cell borders, so there is no
                     doubled line where cells meet and none to strip off the
                     last column. --}}
                <div class="grid grid-cols-7 gap-px bg-gray-200 dark:bg-white/10">
                    @foreach ($this->weeks as $key => $day)
                        <div @class([
                            'relative flex min-h-28 flex-col gap-1 p-2',
                            'bg-white dark:bg-gray-900' => $day['in_month'],
                            'bg-gray-50 dark:bg-gray-900/40' => ! $day['in_month'],
                        ])>
                            {{-- Date number. Today gets a filled disc rather than a
                                 ring around the whole cell, which fought the slot
                                 chips for attention. --}}
                            <div class="flex items-center justify-between">
                                <span @class([
                                    'flex size-6 items-center justify-center rounded-full text-xs font-semibold tabular-nums',
                                    'bg-primary-600 text-white' => $day['is_today'],
                                    'text-gray-700 dark:text-gray-300' => ! $day['is_today'] && $day['in_month'],
                                    'text-gray-400 dark:text-gray-600' => ! $day['is_today'] && ! $day['in_month'],
                                ])>
                                    {{ $day['date']->day }}
                                </span>

                                @php
                                    $free = collect($day['slots'])->where('state', 'free')->count();
                                @endphp

                                @if ($day['in_month'] && $free < 3)
                                    <span class="text-[10px] font-medium tabular-nums text-gray-400 dark:text-gray-500">
                                        {{ 3 - $free }}/3
                                    </span>
                                @endif
                            </div>

                            {{-- Slots --}}
                            <div class="flex flex-col gap-1">
                                @foreach ($day['slots'] as $slotValue => $slot)
                                    @php
                                        $isFree = $slot['state'] === 'free';

                                        $chip = match ($slot['state']) {
                                            'booked' => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-400/10 dark:text-success-300 dark:ring-success-400/30',
                                            'tentative' => 'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-300 dark:ring-warning-400/30',
                                            'blocked' => 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-300 dark:ring-danger-400/30',
                                            default => 'text-gray-300 ring-gray-200/70 dark:text-gray-600 dark:ring-white/5',
                                        };

                                        $initial = strtoupper(substr($slotValue, 0, 1));
                                        $title = $slotNames[$slotValue].' — '.($slot['label'] ?? 'Free')
                                            .($slot['reference'] ? ' ('.$slot['reference'].')' : '');
                                    @endphp

                                    @if ($slot['id'])
                                        <a
                                            href="{{ \App\Filament\Resources\Bookings\BookingResource::getUrl('edit', ['record' => $slot['id']]) }}"
                                            title="{{ $title }}"
                                            class="flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] leading-tight ring-1 ring-inset transition hover:brightness-95 {{ $chip }}"
                                        >
                                            <span class="font-bold">{{ $initial }}</span>
                                            <span class="truncate">{{ $slot['label'] }}</span>
                                        </a>
                                    @else
                                        <span
                                            title="{{ $title }}"
                                            class="flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] leading-tight ring-1 ring-inset {{ $chip }}"
                                        >
                                            <span class="font-bold">{{ $initial }}</span>
                                            <span class="truncate">{{ $slot['label'] ?? '' }}</span>
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            <span class="font-semibold">M</span> morning ·
            <span class="font-semibold">A</span> afternoon ·
            <span class="font-semibold">E</span> evening.
            A full-day booking fills all three.
        </p>
    </x-filament::section>
</x-filament-panels::page>

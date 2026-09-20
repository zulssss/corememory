<x-filament-panels::page>
    {{-- Month navigation --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-semibold text-gray-950 dark:text-white">
            {{ $this->monthStart()->translatedFormat('F Y') }}
        </h2>

        <div class="flex items-center gap-2">
            <x-filament::button wire:click="previousMonth" color="gray" size="sm" icon="heroicon-o-chevron-left">
                Previous
            </x-filament::button>
            <x-filament::button wire:click="today" color="gray" size="sm">Today</x-filament::button>
            <x-filament::button wire:click="nextMonth" color="gray" size="sm" icon="heroicon-o-chevron-right" icon-position="after">
                Next
            </x-filament::button>
        </div>
    </div>

    {{-- Legend --}}
    <div class="flex flex-wrap gap-4 text-xs text-gray-600 dark:text-gray-400">
        @foreach ([
            'booked' => ['Confirmed', 'bg-success-500'],
            'tentative' => ['Enquiry pending', 'bg-warning-500'],
            'blocked' => ['Blocked', 'bg-danger-500'],
            'free' => ['Free', 'bg-gray-200 dark:bg-gray-700'],
        ] as [$label, $colour])
            <span class="flex items-center gap-1.5">
                <span class="size-2.5 rounded-full {{ $colour }}"></span>{{ $label }}
            </span>
        @endforeach
    </div>

    <div class="overflow-x-auto">
        <div class="grid min-w-[56rem] grid-cols-7 gap-px rounded-lg bg-gray-200 p-px dark:bg-gray-700">
            @foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $dayName)
                <div class="bg-gray-50 px-2 py-1.5 text-center text-xs font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    {{ $dayName }}
                </div>
            @endforeach

            @foreach ($this->weeks as $key => $day)
                <div @class([
                    'min-h-24 bg-white p-1.5 dark:bg-gray-900',
                    'opacity-40' => ! $day['in_month'],
                    'ring-2 ring-inset ring-primary-500' => $day['is_today'],
                ])>
                    <div class="mb-1 text-xs font-medium text-gray-700 dark:text-gray-300">
                        {{ $day['date']->day }}
                    </div>

                    <div class="flex flex-col gap-0.5">
                        @foreach ($day['slots'] as $slotValue => $slot)
                            @php
                                $colour = match ($slot['state']) {
                                    'booked' => 'bg-success-100 text-success-800 dark:bg-success-900/40 dark:text-success-300',
                                    'tentative' => 'bg-warning-100 text-warning-800 dark:bg-warning-900/40 dark:text-warning-300',
                                    'blocked' => 'bg-danger-100 text-danger-800 dark:bg-danger-900/40 dark:text-danger-300',
                                    default => 'bg-gray-50 text-gray-400 dark:bg-gray-800 dark:text-gray-600',
                                };
                                $initial = strtoupper(substr($slotValue, 0, 1));
                            @endphp

                            @if ($slot['id'])
                                <a href="{{ \App\Filament\Resources\Bookings\BookingResource::getUrl('edit', ['record' => $slot['id']]) }}"
                                   class="truncate rounded px-1 py-0.5 text-[10px] leading-tight {{ $colour }}"
                                   title="{{ $slot['label'] }} — {{ $slot['reference'] }}">
                                    <span class="font-semibold">{{ $initial }}</span> {{ $slot['label'] }}
                                </a>
                            @else
                                <span class="truncate rounded px-1 py-0.5 text-[10px] leading-tight {{ $colour }}"
                                      title="{{ $slot['label'] ?? 'Free' }}">
                                    <span class="font-semibold">{{ $initial }}</span> {{ $slot['label'] ?? '' }}
                                </span>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>

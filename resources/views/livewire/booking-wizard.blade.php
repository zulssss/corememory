{{--
    THE BOOKING WIZARD

    One step per screen, date first. The running total is visible from the
    package step onwards, and the availability disclaimer appears at the
    calendar step AND again at review — both required by the brief.

    Everything here is keyboard navigable: the calendar is a grid of real
    <button>s, the slots are real radio-style buttons, and nothing depends on
    hover.
--}}
@php
    use App\Enums\SessionSlot;
    use App\Livewire\BookingWizard;

    $steps = __('booking.wizard.steps');
    $quote = $this->quote;
@endphp

<div class="page-gutter py-section">

    {{-- ---------------------------------------------------------------
         Progress indicator
         --------------------------------------------------------------- --}}
    <nav class="rule-b pb-5" aria-label="{{ __('booking.wizard.title') }}">
        <ol class="flex flex-wrap items-center gap-x-6 gap-y-2">
            @foreach ($steps as $number => $label)
                <li>
                    <button
                        type="button"
                        wire:click="goToStep({{ $number }})"
                        @disabled($number >= $step)
                        @class([
                            'micro-label transition-colors',
                            'text-ink' => $number === $step,
                            'hover:text-ink cursor-pointer' => $number < $step,
                            'cursor-default' => $number > $step,
                        ])
                        @if ($number === $step) aria-current="step" @endif
                    >
                        <span class="tabular-nums">{{ str_pad((string) $number, 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="ml-1.5">{{ $label }}</span>
                    </button>
                </li>
            @endforeach
        </ol>
    </nav>

    <p class="sr-only" aria-live="polite">
        {{ __('booking.wizard.step_of', ['current' => $step, 'total' => count($steps)]) }}
    </p>

    {{-- A clash, a stale tab, or rate limiting. Stated plainly, never hidden. --}}
    @if ($clashError)
        <div role="alert" class="mt-8 border border-critical bg-paper-raised p-5">
            <x-micro-label class="text-critical">{{ __('booking.availability.blocked') }}</x-micro-label>
            <p class="mt-2 text-body-sm text-ink">{{ $clashError }}</p>
        </div>
    @endif

    <div class="grid gap-gutter pt-10 md:grid-cols-12">

        {{-- =========================================================
             MAIN COLUMN
             ========================================================= --}}
        <div class="md:col-span-7">

            {{-- ------------------------------------------------------
                 STEP 1 — DATE AND SESSION, ALWAYS FIRST
                 ------------------------------------------------------ --}}
            @if ($step === BookingWizard::STEP_DATES)
                <h1 class="text-statement font-medium text-ink">{{ __('booking.wizard.headline') }}</h1>
                <p class="mt-4 max-w-measure text-body text-ink-muted">{{ __('booking.wizard.intro') }}</p>

                {{-- Your chosen dates --}}
                <div class="mt-10 flex flex-col gap-4">
                    @foreach ($dates as $index => $date)
                        <div @class([
                            'rule-all p-4 transition-colors',
                            'border-ink' => $activeDate === $index,
                        ])>
                            <div class="flex items-start justify-between gap-4">
                                <button type="button" wire:click="focusDate({{ $index }})" class="text-left">
                                    <x-micro-label>{{ __('booking.wizard.date_number', ['number' => $index + 1]) }}</x-micro-label>
                                    <p class="mt-1 text-body font-medium text-ink">
                                        @if ($date['event_date'] && $date['session_slot'])
                                            {{ \Carbon\CarbonImmutable::parse($date['event_date'])->translatedFormat('d M Y') }}
                                            — {{ SessionSlot::from($date['session_slot'])->label() }}
                                        @elseif ($date['event_date'])
                                            {{ \Carbon\CarbonImmutable::parse($date['event_date'])->translatedFormat('d M Y') }}
                                            — <span class="text-ink-muted">{{ __('booking.wizard.choose_a_session') }}</span>
                                        @else
                                            <span class="text-ink-muted">{{ __('booking.wizard.no_date_yet') }}</span>
                                        @endif
                                    </p>
                                </button>

                                @if (count($dates) > 1)
                                    <button type="button" wire:click="removeDate({{ $index }})"
                                            class="micro-label shrink-0 hover:text-critical">
                                        {{ __('booking.wizard.remove_date') }}
                                    </button>
                                @endif
                            </div>

                            @if (count($dates) > 1)
                                <input type="text" wire:model.blur="dates.{{ $index }}.label"
                                       placeholder="{{ __('booking.wizard.date_label_hint') }}"
                                       aria-label="{{ __('booking.fields.label') }}"
                                       class="mt-3 w-full border border-rule bg-paper px-3 py-2 text-body-sm text-ink placeholder:text-ink-faint focus:border-ink">
                            @endif
                        </div>
                    @endforeach

                    @if (count($dates) < 4)
                        <button type="button" wire:click="addDate" class="micro-label self-start hover:text-ink">
                            + {{ __('booking.wizard.add_another_date') }}
                        </button>
                    @endif
                </div>

                {{-- Calendar --}}
                <div class="mt-10">
                    <div class="rule-b flex items-center justify-between pb-3">
                        <x-micro-label>{{ __('booking.wizard.choose_a_day') }}</x-micro-label>

                        <div class="flex items-center gap-5">
                            <button type="button" wire:click="previousMonth" class="micro-label hover:text-ink"
                                    aria-label="{{ __('booking.wizard.previous_month') }}">←</button>
                            <span class="font-mono text-caption uppercase tracking-micro text-ink">
                                {{ \Carbon\CarbonImmutable::createFromFormat('Y-m', $calendarMonth)->translatedFormat('F Y') }}
                            </span>
                            <button type="button" wire:click="nextMonth" class="micro-label hover:text-ink"
                                    aria-label="{{ __('booking.wizard.next_month') }}">→</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-7 border-l border-t border-rule" role="grid">
                        @foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $dayName)
                            <div class="rule-b rule-r px-2 py-2 text-center" role="columnheader">
                                <x-micro-label>{{ $dayName }}</x-micro-label>
                            </div>
                        @endforeach

                        @foreach ($this->calendar as $key => $day)
                            @php
                                $carbon = \Carbon\CarbonImmutable::parse($key);
                                $inMonth = $carbon->format('Y-m') === $calendarMonth;
                                $anyBookable = collect($day['slots'])->contains(fn ($s) => $s->bookable);
                                $isChosen = ($dates[$activeDate]['event_date'] ?? null) === $key;
                            @endphp

                            <button
                                type="button"
                                role="gridcell"
                                wire:click="selectDay('{{ $key }}')"
                                @disabled(! $anyBookable)
                                @class([
                                    'rule-b rule-r aspect-square p-2 text-left transition-colors',
                                    'opacity-35' => ! $inMonth,
                                    'cursor-not-allowed text-ink-faint' => ! $anyBookable,
                                    'hover:bg-paper-sunken' => $anyBookable && ! $isChosen,
                                    'bg-ink text-paper' => $isChosen,
                                ])
                                @if ($isChosen) aria-current="date" @endif
                                aria-label="{{ $carbon->translatedFormat('d F Y') }}{{ $anyBookable ? '' : ' — '.($day['window_reason'] ?? __('booking.availability.blocked')) }}"
                            >
                                <span class="block text-body-sm tabular-nums">{{ $carbon->day }}</span>

                                @if ($anyBookable && $inMonth)
                                    @php $free = collect($day['slots'])->filter(fn ($s) => $s->bookable && $s->slot !== SessionSlot::FullDay)->count(); @endphp
                                    <span class="mt-0.5 block font-mono text-[9px] uppercase tracking-micro {{ $isChosen ? 'text-paper/70' : 'text-ink-faint' }}">
                                        {{ $free }}/3
                                    </span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Session slots for the chosen day --}}
                @if ($chosenDay = ($dates[$activeDate]['event_date'] ?? null))
                    <div class="mt-10">
                        <x-micro-label class="rule-b block pb-3">{{ __('booking.wizard.choose_a_session') }}</x-micro-label>

                        <div class="mt-4 flex flex-col gap-3" role="radiogroup" aria-label="{{ __('booking.wizard.choose_a_session') }}">
                            @foreach ($this->calendar[$chosenDay]['slots'] ?? [] as $slotValue => $state)
                                @php $isSelected = ($dates[$activeDate]['session_slot'] ?? null) === $slotValue; @endphp

                                <button
                                    type="button"
                                    role="radio"
                                    aria-checked="{{ $isSelected ? 'true' : 'false' }}"
                                    wire:click="selectSlot('{{ $slotValue }}')"
                                    @disabled(! $state->bookable)
                                    @class([
                                        'rule-all flex items-baseline justify-between gap-4 px-4 py-3.5 text-left transition-colors',
                                        'border-ink bg-ink text-paper' => $isSelected,
                                        'cursor-not-allowed opacity-50' => ! $state->bookable,
                                        'hover:border-ink' => $state->bookable && ! $isSelected,
                                    ])
                                >
                                    <span class="text-body-sm font-medium">{{ $state->label }}</span>

                                    {{-- Unavailable options stay visible WITH THEIR REASON
                                         rather than disappearing — the brief is explicit. --}}
                                    @if ($state->reason)
                                        <span @class([
                                            'font-mono text-micro uppercase tracking-micro',
                                            'text-paper/70' => $isSelected,
                                            'text-ink-muted' => ! $isSelected,
                                        ])>{{ $state->reason }}</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Venue for this date --}}
                @if ($dates[$activeDate]['session_slot'] ?? null)
                    <div class="mt-8 grid gap-4 sm:grid-cols-3">
                        @foreach (['venue' => 'venue', 'city' => 'city', 'state' => 'state'] as $field => $key)
                            <label class="flex flex-col gap-1.5">
                                <x-micro-label>{{ __('booking.fields.'.$key) }}</x-micro-label>
                                <input type="text" wire:model.blur="dates.{{ $activeDate }}.{{ $field }}"
                                       class="border border-rule bg-paper px-3 py-2 text-body-sm text-ink focus:border-ink">
                            </label>
                        @endforeach
                    </div>
                @endif

                {{-- REQUIRED DISCLAIMER — calendar step --}}
                <div class="rule-t mt-10 pt-5">
                    <x-micro-label>{{ __('booking.availability.available') }}</x-micro-label>
                    <p class="mt-2 max-w-measure text-body-sm text-ink-soft">
                        {{ __('booking.availability_disclaimer') }}
                    </p>
                </div>
            @endif

            {{-- ------------------------------------------------------
                 STEP 2 — PACKAGE
                 ------------------------------------------------------ --}}
            @if ($step === BookingWizard::STEP_PACKAGE)
                <h1 class="text-statement font-medium text-ink">{{ __('site.sections.our_packages') }}</h1>

                <div class="mt-8 flex flex-col gap-4">
                    @foreach ($packages as $package)
                        @php $isSelected = $packageId === $package->id; @endphp

                        <button
                            type="button"
                            wire:click="selectPackage({{ $package->id }})"
                            @class([
                                'rule-all p-5 text-left transition-colors',
                                'border-ink' => $isSelected,
                                'hover:border-ink-faint' => ! $isSelected,
                            ])
                            aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                        >
                            <div class="flex items-baseline justify-between gap-4">
                                <h2 class="text-body-lg font-medium text-ink">
                                    {{ $package->name }}
                                    @if ($package->is_popular)
                                        <span class="ml-2 font-mono text-micro uppercase tracking-micro text-accent">
                                            {{ __('site.packages.popular') }}
                                        </span>
                                    @endif
                                </h2>

                                <span class="shrink-0 text-body-lg font-medium tabular-nums text-ink">
                                    {{ $package->displayPrice() }}
                                </span>
                            </div>

                            @if ($package->description)
                                <p class="mt-2 text-body-sm text-ink-muted">{{ $package->description }}</p>
                            @endif

                            <ul class="mt-4 grid gap-1.5 sm:grid-cols-2">
                                @foreach ($package->inclusions ?? [] as $inclusion)
                                    <li class="flex gap-2 text-body-sm text-ink-soft">
                                        <span aria-hidden="true" class="text-ink-faint">—</span>
                                        <span>{{ $inclusion }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </button>
                    @endforeach
                </div>

                @error('packageId')
                    <p role="alert" class="mt-4 text-body-sm text-critical">{{ $message }}</p>
                @enderror
            @endif

            {{-- ------------------------------------------------------
                 STEP 3 — ADD-ONS
                 ------------------------------------------------------ --}}
            @if ($step === BookingWizard::STEP_ADDONS)
                <h1 class="text-statement font-medium text-ink">{{ __('site.packages.add_ons') }}</h1>
                <p class="mt-4 max-w-measure text-body text-ink-muted">{{ __('booking.wizard.add_ons_intro') }}</p>

                <div class="rule-t mt-8">
                    @foreach ($this->availableAddOns as $addOn)
                        @php
                            $qty = $addOns[$addOn->id] ?? 0;
                            $isOn = $qty > 0;
                        @endphp

                        <div class="rule-b flex items-center justify-between gap-5 py-4">
                            <div class="min-w-0">
                                <h3 class="text-body font-medium text-ink">{{ $addOn->name }}</h3>
                                @if ($addOn->description)
                                    <p class="mt-1 text-body-sm text-ink-muted">{{ $addOn->description }}</p>
                                @endif
                                <span class="mt-1 block font-mono text-micro uppercase tracking-micro text-ink-muted">
                                    {{ $addOn->price_cents->formatCompact() }}{{ $addOn->is_quantifiable ? ' '.__('site.packages.each') : '' }}
                                </span>
                            </div>

                            <div class="shrink-0">
                                {{-- Quantifiable add-ons get a stepper; the rest a toggle. --}}
                                @if ($addOn->is_quantifiable)
                                    <div class="flex items-center gap-1" role="group" aria-label="{{ $addOn->name }}">
                                        <button type="button"
                                                wire:click="setAddOnQuantity({{ $addOn->id }}, {{ $qty - 1 }})"
                                                @disabled($qty < 1)
                                                class="rule-all h-9 w-9 text-body text-ink disabled:opacity-35"
                                                aria-label="−">−</button>

                                        <span class="w-10 text-center text-body tabular-nums text-ink" aria-live="polite">{{ $qty }}</span>

                                        <button type="button"
                                                wire:click="setAddOnQuantity({{ $addOn->id }}, {{ $qty + 1 }})"
                                                @disabled($qty >= $addOn->max_qty)
                                                class="rule-all h-9 w-9 text-body text-ink disabled:opacity-35"
                                                aria-label="+">+</button>
                                    </div>
                                @else
                                    <button type="button"
                                            wire:click="toggleAddOn({{ $addOn->id }})"
                                            aria-pressed="{{ $isOn ? 'true' : 'false' }}"
                                            @class([
                                                'rule-all px-4 py-2 font-mono text-micro uppercase tracking-micro transition-colors',
                                                'border-ink bg-ink text-paper' => $isOn,
                                                'text-ink hover:border-ink' => ! $isOn,
                                            ])>
                                        {{ $isOn ? __('booking.wizard.selected') : '+' }}
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- ------------------------------------------------------
                 STEP 4 — DETAILS
                 ------------------------------------------------------ --}}
            @if ($step === BookingWizard::STEP_DETAILS)
                <h1 class="text-statement font-medium text-ink">{{ __('booking.wizard.your_details') }}</h1>

                <div class="mt-8 grid gap-5 sm:grid-cols-2">
                    @foreach ([
                        ['partnerOneName', 'partner_one', 'text', true],
                        ['partnerTwoName', 'partner_two', 'text', false],
                        ['email', 'email', 'email', true],
                        ['phone', 'phone', 'tel', true],
                    ] as [$model, $key, $type, $required])
                        <label class="flex flex-col gap-1.5">
                            <x-micro-label>
                                {{ __('booking.fields.'.$key) }}
                                @unless ($required)
                                    <span class="text-ink-faint">({{ __('booking.fields.optional') }})</span>
                                @endunless
                            </x-micro-label>

                            <input type="{{ $type }}" wire:model.blur="{{ $model }}"
                                   @if ($key === 'phone') placeholder="012-345 6789" @endif
                                   @class([
                                       'border bg-paper px-3 py-2.5 text-body text-ink focus:border-ink',
                                       'border-critical' => $errors->has($model),
                                       'border-rule' => ! $errors->has($model),
                                   ])>

                            @error($model)
                                <span role="alert" class="text-body-sm text-critical">{{ $message }}</span>
                            @enderror
                        </label>
                    @endforeach

                    <label class="flex flex-col gap-1.5">
                        <x-micro-label>{{ __('booking.fields.guest_count') }}</x-micro-label>
                        <input type="number" min="1" wire:model.blur="guestCount"
                               class="border border-rule bg-paper px-3 py-2.5 text-body text-ink focus:border-ink">
                    </label>

                    <label class="flex flex-col gap-1.5">
                        <x-micro-label>{{ __('booking.fields.source') }}</x-micro-label>
                        <select wire:model.blur="source"
                                class="border border-rule bg-paper px-3 py-2.5 text-body text-ink focus:border-ink">
                            <option value="">—</option>
                            @foreach ($sources as $sourceOption)
                                <option value="{{ $sourceOption->value }}">{{ $sourceOption->label() }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="flex flex-col gap-1.5 sm:col-span-2">
                        <x-micro-label>{{ __('booking.fields.notes') }}</x-micro-label>
                        <textarea wire:model.blur="notes" rows="4"
                                  class="border border-rule bg-paper px-3 py-2.5 text-body text-ink focus:border-ink"></textarea>
                    </label>
                </div>
            @endif

            {{-- ------------------------------------------------------
                 STEP 5 — REVIEW
                 ------------------------------------------------------ --}}
            @if ($step === BookingWizard::STEP_REVIEW)
                <h1 class="text-statement font-medium text-ink">{{ __('booking.wizard.review_headline') }}</h1>

                {{-- Dates --}}
                <div class="rule-t mt-8 pt-5">
                    <x-micro-label>{{ __('booking.wizard.your_dates') }}</x-micro-label>
                    <ul class="mt-3 flex flex-col gap-2">
                        @foreach ($this->completedDates() as $date)
                            <li class="text-body text-ink">
                                {{ \Carbon\CarbonImmutable::parse($date['event_date'])->translatedFormat('d M Y') }}
                                — {{ SessionSlot::from($date['session_slot'])->labelWithTime() }}
                                @if ($date['label'])
                                    <span class="text-ink-muted">({{ $date['label'] }})</span>
                                @endif
                                @if ($date['venue'])
                                    <span class="block text-body-sm text-ink-muted">{{ $date['venue'] }}{{ $date['city'] ? ', '.$date['city'] : '' }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- Details --}}
                <div class="rule-t mt-6 pt-5">
                    <x-micro-label>{{ __('booking.wizard.your_details') }}</x-micro-label>
                    <dl class="mt-3 grid gap-2 sm:grid-cols-2">
                        @foreach ([
                            __('booking.fields.partner_one') => $partnerOneName,
                            __('booking.fields.partner_two') => $partnerTwoName ?: '—',
                            __('booking.fields.email') => $email,
                            __('booking.fields.phone') => $phone,
                            __('booking.fields.guest_count') => $guestCount ?: '—',
                        ] as $label => $value)
                            <div>
                                <dt class="micro-label">{{ $label }}</dt>
                                <dd class="text-body-sm text-ink">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    @if ($notes)
                        <p class="mt-4 max-w-measure text-body-sm text-ink-soft">{{ $notes }}</p>
                    @endif
                </div>

                {{-- REQUIRED DISCLAIMER — review step --}}
                <div class="rule-t mt-6 pt-5">
                    <x-micro-label>{{ __('booking.availability.available') }}</x-micro-label>
                    <p class="mt-2 max-w-measure text-body-sm text-ink-soft">
                        {{ __('booking.availability_disclaimer') }}
                    </p>
                </div>

                {{-- Terms --}}
                <label class="mt-8 flex cursor-pointer items-start gap-3">
                    <input type="checkbox" wire:model.live="terms"
                           class="mt-1 h-4 w-4 shrink-0 accent-[var(--color-ink)]">
                    <span class="text-body-sm text-ink-soft">{{ __('booking.wizard.terms_label') }}</span>
                </label>

                @error('terms')
                    <p role="alert" class="mt-2 text-body-sm text-critical">{{ $message }}</p>
                @enderror

                {{-- Honeypot. Hidden from people, irresistible to bots.
                     Not display:none — some bots skip those — and never focusable. --}}
                <div aria-hidden="true" class="absolute left-[-9999px] h-px w-px overflow-hidden">
                    <label>
                        Website
                        <input type="text" tabindex="-1" autocomplete="off" wire:model="website_url">
                    </label>
                </div>
            @endif

            {{-- ------------------------------------------------------
                 Navigation
                 ------------------------------------------------------ --}}
            <div class="rule-t mt-12 flex items-center justify-between gap-4 pt-6">
                <button type="button" wire:click="previousStep"
                        @class(['micro-label hover:text-ink', 'invisible' => $step === BookingWizard::STEP_DATES])>
                    ← {{ __('booking.wizard.back') }}
                </button>

                @if ($step < BookingWizard::STEP_REVIEW)
                    <button type="button" wire:click="nextStep"
                            class="border border-ink px-6 py-3.5 font-mono text-micro uppercase tracking-micro text-ink transition-colors hover:bg-ink hover:text-paper">
                        {{ __('booking.wizard.next') }} →
                    </button>
                @else
                    <button type="button" wire:click="submit" wire:loading.attr="disabled"
                            class="border border-ink bg-ink px-6 py-3.5 font-mono text-micro uppercase tracking-micro text-paper transition-opacity disabled:opacity-50">
                        <span wire:loading.remove wire:target="submit">↗ {{ __('booking.wizard.submit') }}</span>
                        <span wire:loading wire:target="submit">…</span>
                    </button>
                @endif
            </div>
        </div>

        {{-- =========================================================
             SUMMARY — the running total, visible from step 2 onwards
             ========================================================= --}}
        <aside class="md:col-span-4 md:col-start-9">
            <div class="rule-all sticky top-[calc(var(--header-height)+1.5rem)] p-5">
                <x-micro-label>{{ __('booking.wizard.running_total') }}</x-micro-label>

                @if ($this->package)
                    <div class="rule-b flex items-baseline justify-between gap-4 py-4">
                        <span class="text-body-sm text-ink">{{ $this->package->name }}</span>
                        <span class="shrink-0 text-body-sm tabular-nums text-ink">{{ $quote->subtotal->formatCompact() }}</span>
                    </div>

                    @if ($quote->hasAddOns())
                        @foreach ($quote->lines as $line)
                            <div class="rule-b flex items-baseline justify-between gap-4 py-3">
                                <span class="text-body-sm text-ink-muted">{{ $line->describe() }}</span>
                                <span class="shrink-0 text-body-sm tabular-nums text-ink-muted">{{ $line->lineTotal->formatCompact() }}</span>
                            </div>
                        @endforeach
                    @endif

                    <div class="flex items-baseline justify-between gap-4 pt-5">
                        <span class="text-body font-medium text-ink">{{ __('booking.wizard.running_total') }}</span>
                        <span class="shrink-0 text-body-lg font-medium tabular-nums text-ink">{{ $quote->total->formatCompact() }}</span>
                    </div>

                    <div class="mt-3 flex items-baseline justify-between gap-4">
                        <x-micro-label>{{ __('booking.wizard.deposit_line', ['percent' => (int) $quote->depositPercent]) }}</x-micro-label>
                        <span class="shrink-0 text-body-sm tabular-nums text-ink">{{ $quote->deposit->formatCompact() }}</span>
                    </div>

                    <div class="mt-1.5 flex items-baseline justify-between gap-4">
                        <x-micro-label>{{ __('booking.wizard.balance_line') }}</x-micro-label>
                        <span class="shrink-0 text-body-sm tabular-nums text-ink-muted">{{ $quote->balance()->formatCompact() }}</span>
                    </div>
                @else
                    <p class="mt-4 text-body-sm text-ink-muted">{{ __('booking.errors.package_required') }}</p>
                @endif

                {{-- A request is not a confirmation. Said here, on every step. --}}
                <p class="rule-t mt-5 pt-4 text-body-sm text-ink-muted">
                    {{ __('booking.not_a_confirmation') }}
                </p>
            </div>
        </aside>
    </div>
</div>

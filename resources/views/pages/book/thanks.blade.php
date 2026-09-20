{{--
    The thank-you page.

    Reference, what happens next, expected response time, and a WhatsApp link.
    It repeats that this is a REQUEST, not a confirmation — a couple who walks
    away believing their date is locked is the worst outcome this whole system
    exists to prevent.

    Deliberately shows no personal details: a reference is guessable, so this
    page must not leak a couple's phone number to whoever tries CM-2026-0002.
--}}
@php use App\Support\Settings; @endphp

<x-layouts.app :title="__('booking.thanks.headline').' — '.__('site.brand.name')">

    <x-section size="lg">
        <div class="grid gap-gutter md:grid-cols-12">
            <div class="md:col-span-7">
                <x-reveal>
                    <x-micro-label>{{ __('booking.thanks.label') }}</x-micro-label>
                </x-reveal>

                <x-reveal>
                    <h1 class="mt-4 text-statement-lg font-medium text-ink">
                        {{ __('booking.thanks.headline') }}
                    </h1>
                </x-reveal>

                {{-- The reference, given prominence — it is what the couple
                     quotes back to the studio. --}}
                <x-reveal>
                    <div class="rule-all mt-8 inline-block px-6 py-4">
                        <x-micro-label>{{ __('booking.thanks.reference') }}</x-micro-label>
                        <p class="mt-1 text-statement font-medium tabular-nums text-ink">{{ $booking->reference }}</p>
                    </div>
                </x-reveal>

                <x-reveal>
                    <p class="mt-8 max-w-measure text-body text-ink-soft">
                        {{ __('booking.not_a_confirmation') }}
                    </p>
                </x-reveal>

                {{-- What happens next --}}
                <div class="rule-t mt-10 pt-6">
                    <x-micro-label>{{ __('booking.thanks.what_next') }}</x-micro-label>

                    <ol class="mt-5 flex flex-col gap-4" data-reveal-group>
                        @foreach (__('booking.thanks.steps') as $i => $stepText)
                            <x-reveal as="li" class="flex gap-5">
                                <span class="font-mono text-caption tabular-nums text-ink-faint">
                                    {{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}
                                </span>
                                <span class="max-w-measure text-body text-ink-soft">{{ $stepText }}</span>
                            </x-reveal>
                        @endforeach
                    </ol>

                    <p class="mt-6 text-body-sm text-ink-muted">{{ __('booking.thanks.response_time') }}</p>
                </div>

                {{-- The availability disclaimer, a third time, as required --}}
                <div class="rule-t mt-8 pt-5">
                    <x-micro-label>{{ __('booking.availability.available') }}</x-micro-label>
                    <p class="mt-2 max-w-measure text-body-sm text-ink-soft">
                        {{ __('booking.availability_disclaimer') }}
                    </p>
                </div>

                <div class="mt-10 flex flex-wrap gap-4">
                    @if ($whatsapp = Settings::get('contact.whatsapp'))
                        <a href="https://wa.me/{{ preg_replace('/\D+/', '', $whatsapp) }}"
                           class="border border-ink px-6 py-3.5 font-mono text-micro uppercase tracking-micro text-ink transition-colors hover:bg-ink hover:text-paper">
                            ↗ {{ __('booking.thanks.whatsapp') }}
                        </a>
                    @endif

                    <a href="{{ route('home') }}" class="micro-label self-center hover:text-ink">
                        {{ __('booking.thanks.back_home') }}
                    </a>
                </div>
            </div>

            {{-- Summary of what was requested --}}
            <div class="md:col-span-4 md:col-start-9">
                <div class="rule-all p-5">
                    <x-micro-label>{{ __('booking.wizard.your_dates') }}</x-micro-label>

                    <ul class="mt-3 flex flex-col gap-3">
                        @foreach ($booking->dates as $date)
                            <li class="text-body-sm text-ink">
                                {{ $date->event_date->translatedFormat('d M Y') }}
                                <span class="block text-ink-muted">{{ $date->session_slot->labelWithTime() }}</span>
                                @if ($date->venue)
                                    <span class="block text-ink-muted">{{ $date->venue }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>

                    @if ($booking->package)
                        <div class="rule-t mt-5 pt-4">
                            <x-micro-label>{{ __('booking.wizard.your_package') }}</x-micro-label>
                            <p class="mt-2 text-body-sm text-ink">{{ $booking->package->name }}</p>
                        </div>
                    @endif

                    <div class="rule-t mt-5 flex items-baseline justify-between gap-4 pt-4">
                        <x-micro-label>{{ __('booking.wizard.running_total') }}</x-micro-label>
                        <span class="text-body font-medium tabular-nums text-ink">
                            {{ $booking->estimated_total_cents->formatCompact() }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </x-section>

</x-layouts.app>

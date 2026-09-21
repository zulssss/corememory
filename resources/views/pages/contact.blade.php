{{--
    /contact — a short form, a WhatsApp link, and the studio address.

    A plain HTML POST with a real Form Request, so it works with JavaScript
    disabled. The booking wizard is pointed at first, because a couple who
    already has a date is better served there.
--}}
@php use App\Support\Settings; @endphp

<x-layouts.app
    :title="__('site.nav.contact').' — '.__('site.brand.name')"
    :description="__('contact.meta_description')"
>

    <x-section size="lg">
        <div class="grid gap-gutter md:grid-cols-12">

            {{-- Intro + form --}}
            <div class="md:col-span-7">
                <x-reveal><x-micro-label>{{ __('site.nav.contact') }}</x-micro-label></x-reveal>

                <x-reveal>
                    <h1 class="mt-4 text-statement-lg font-medium text-ink">{{ __('contact.headline') }}</h1>
                </x-reveal>

                <x-reveal>
                    <p class="mt-6 max-w-measure text-body text-ink-muted">{{ __('contact.intro') }}</p>
                </x-reveal>

                {{-- Success message. role=status so screen readers announce it
                     without stealing focus. --}}
                @if (session('enquiry_sent'))
                    <div role="status" class="rule-all mt-8 p-5">
                        <x-micro-label>{{ __('contact.sent.label') }}</x-micro-label>
                        <p class="mt-2 text-body-sm text-ink">{{ __('contact.sent.body') }}</p>
                    </div>
                @endif

                <form method="POST" action="{{ route('contact.store') }}" class="mt-10 flex flex-col gap-5">
                    @csrf

                    @foreach ([
                        ['name', 'text', true],
                        ['email', 'email', true],
                        ['phone', 'tel', false],
                    ] as [$field, $type, $required])
                        <label class="flex flex-col gap-1.5">
                            <x-micro-label>
                                {{ __('contact.form.'.$field) }}
                                @unless ($required)
                                    <span class="text-ink-faint">({{ __('contact.form.phone_optional') }})</span>
                                @endunless
                            </x-micro-label>

                            <input
                                type="{{ $type }}"
                                name="{{ $field }}"
                                value="{{ old($field) }}"
                                @required($required)
                                @if ($errors->has($field)) aria-invalid="true" aria-describedby="{{ $field }}-error" @endif
                                @class([
                                    'border bg-paper px-3 py-2.5 text-body text-ink focus:border-ink',
                                    'border-critical' => $errors->has($field),
                                    'border-input-border' => ! $errors->has($field),
                                ])>

                            @error($field)
                                <span id="{{ $field }}-error" class="text-body-sm text-critical">{{ $message }}</span>
                            @enderror
                        </label>
                    @endforeach

                    <label class="flex flex-col gap-1.5">
                        <x-micro-label>{{ __('contact.form.message') }}</x-micro-label>
                        <textarea
                            name="message"
                            rows="6"
                            required
                            placeholder="{{ __('contact.form.message_placeholder') }}"
                            @if ($errors->has('message')) aria-invalid="true" aria-describedby="message-error" @endif
                            @class([
                                'border bg-paper px-3 py-2.5 text-body text-ink placeholder:text-ink-faint focus:border-ink',
                                'border-critical' => $errors->has('message'),
                                'border-input-border' => ! $errors->has('message'),
                            ])>{{ old('message') }}</textarea>

                        @error('message')
                            <span id="message-error" class="text-body-sm text-critical">{{ $message }}</span>
                        @enderror
                    </label>

                    {{-- Honeypot. Off-screen rather than display:none — some bots
                         skip hidden fields — and never focusable. --}}
                    <div aria-hidden="true" class="absolute left-[-9999px] h-px w-px overflow-hidden">
                        <label>
                            Website
                            <input type="text" name="{{ config('booking.honeypot_field') }}" tabindex="-1" autocomplete="off">
                        </label>
                    </div>

                    <button type="submit"
                            class="self-start border border-ink bg-ink px-6 py-3.5 font-mono text-micro uppercase tracking-micro text-paper transition-opacity hover:opacity-85">
                        ↗ {{ __('contact.form.submit') }}
                    </button>

                    <p class="text-body-sm text-ink-muted">{{ __('contact.response_time') }}</p>
                </form>
            </div>

            {{-- Studio details --}}
            <div class="md:col-span-4 md:col-start-9">
                <div class="rule-all p-5">
                    <x-micro-label>{{ __('contact.prefer_booking') }}</x-micro-label>
                    <a href="{{ route('book') }}"
                       class="mt-3 inline-block border border-ink px-4 py-2.5 font-mono text-micro uppercase tracking-micro text-ink transition-colors hover:bg-ink hover:text-paper">
                        ↗ {{ __('contact.prefer_booking_cta') }}
                    </a>
                </div>

                <div class="rule-t mt-8 pt-6">
                    <x-micro-label>{{ __('contact.studio') }}</x-micro-label>

                    <div class="mt-3 flex flex-col gap-2 text-body-sm text-ink">
                        @if ($email = Settings::get('contact.email'))
                            <a href="mailto:{{ $email }}" class="hover:text-accent">{{ $email }}</a>
                        @endif

                        @if ($phone = Settings::get('contact.phone'))
                            <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="hover:text-accent">{{ $phone }}</a>
                        @endif
                    </div>

                    @if ($whatsapp = Settings::get('contact.whatsapp'))
                        <a href="https://wa.me/{{ preg_replace('/\D+/', '', $whatsapp) }}"
                           class="mt-4 inline-block font-mono text-micro uppercase tracking-micro text-accent hover:text-ink">
                            ↗ {{ __('contact.whatsapp') }}
                        </a>
                    @endif
                </div>

                @if ($address = Settings::get('contact.address'))
                    <div class="rule-t mt-6 pt-6">
                        <x-micro-label>{{ __('contact.find_us') }}</x-micro-label>
                        <address class="mt-3 not-italic text-body-sm text-ink-soft">
                            {!! nl2br(e($address)) !!}
                        </address>
                    </div>
                @endif
            </div>
        </div>
    </x-section>

</x-layouts.app>

{{--
    /packages — prices in full, visible without a single interaction.

    Desktop gets a comparison table so tiers can be read against each other.
    Mobile gets stacked cards, because a 4-column table on a 360px phone is
    unreadable and most of this site's traffic arrives from an Instagram bio
    link on a phone.
--}}
<x-layouts.app
    :title="__('site.nav.packages').' — '.__('site.brand.name')"
    :description="__('site.packages.meta_description')"
    :schema="[
        App\Support\Seo::services($packages),
        App\Support\Seo::breadcrumbs([
            __('site.nav.home') => route('home'),
            __('site.nav.packages') => route('packages'),
        ]),
    ]"
>

    {{-- Intro --}}
    <x-section size="intro" class="pb-0">
        <div class="grid gap-8 md:grid-cols-12">
            <div class="md:col-span-7">
                <x-reveal>
                    <x-micro-label>{{ __('site.sections.our_packages') }}</x-micro-label>
                </x-reveal>
                <x-reveal>
                    <h1 class="mt-4 text-statement-lg font-medium text-ink">
                        {{ __('site.packages.headline') }}
                    </h1>
                </x-reveal>
            </div>

            <div class="md:col-span-4 md:col-start-9">
                <x-reveal>
                    <p class="text-body text-ink-muted">{{ __('site.packages.intro') }}</p>
                </x-reveal>
            </div>
        </div>
    </x-section>

    {{-- ---------------------------------------------------------------
         MOBILE — stacked cards
         --------------------------------------------------------------- --}}
    <x-section class="md:hidden">
        @foreach ($groups as $group)
            <div class="mb-12 last:mb-0">
                <x-reveal>
                    <h2 class="rule-b pb-3 text-statement font-medium text-ink">{{ $group['category']->label() }}</h2>
                    <p class="mt-3 text-body-sm text-ink-muted">{{ $group['category']->description() }}</p>
                </x-reveal>

                <div class="mt-8 flex flex-col gap-10">
            @foreach ($group['packages'] as $i => $package)
                <x-reveal>
                    <article @class(['rule-all p-6', 'border-ink' => $package->is_popular])>
                        <div class="flex items-baseline justify-between gap-4">
                            <x-micro-label>{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</x-micro-label>
                            @if ($package->is_popular)
                                <x-micro-label class="text-accent">{{ __('site.packages.popular') }}</x-micro-label>
                            @endif
                        </div>

                        <h2 class="mt-3 text-statement font-medium text-ink">{{ $package->name }}</h2>

                        <p class="mt-3 text-body-lg font-medium tabular-nums text-ink">
                            {{ $package->displayPrice() }}
                        </p>

                        @if ($package->description)
                            <p class="mt-3 text-body-sm text-ink-muted">{{ $package->description }}</p>
                        @endif

                        <x-micro-label class="mt-6 block">{{ __('site.packages.whats_included') }}</x-micro-label>
                        <ul class="mt-3 flex flex-col gap-2">
                            @foreach ($package->inclusions ?? [] as $inclusion)
                                <li class="flex gap-3 text-body-sm text-ink-soft">
                                    <span aria-hidden="true" class="text-ink-faint">—</span>
                                    <span>{{ $inclusion }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <a href="{{ route('book', ['package' => $package->slug]) }}"
                           class="mt-6 inline-block border border-ink px-5 py-3 font-mono text-micro uppercase tracking-micro text-ink">
                            ↗ {{ __('site.packages.choose') }}
                        </a>
                    </article>
                </x-reveal>
            @endforeach
                </div>
            </div>
        @endforeach
    </x-section>

    {{-- ---------------------------------------------------------------
         DESKTOP — comparison table
         A real <table> so it is announced correctly by screen readers and
         each price is tied to its package by row and column headers.
         --------------------------------------------------------------- --}}
    <x-section class="hidden md:block" :label="__('site.packages.compare')">
        {{-- One table per coverage type. Eighteen packages in a single table
             would be eighteen columns wide; four or six is comparable. --}}
        @foreach ($groups as $group)
        @php
            $packages = $group['packages'];

            // Union of every inclusion across THIS category, preserving order
            // of first appearance, so the comparison rows line up.
            $allInclusions = collect($packages)
                ->flatMap(fn ($p) => $p->inclusions ?? [])
                ->unique()
                ->values();
        @endphp

        <div class="mb-16 last:mb-0">
            <x-reveal>
                <h2 class="text-statement font-medium text-ink">{{ $group['category']->label() }}</h2>
                <p class="mt-2 max-w-measure text-body-sm text-ink-muted">{{ $group['category']->description() }}</p>
            </x-reveal>

        <div class="mt-6 overflow-x-auto">
            <table class="w-full min-w-[48rem] border-collapse text-left">
                <caption class="sr-only">{{ __('site.packages.compare') }}</caption>

                <thead>
                    <tr>
                        <th scope="col" class="rule-b w-56 py-5 align-bottom">
                            <x-micro-label>{{ __('site.packages.whats_included') }}</x-micro-label>
                        </th>

                        @foreach ($packages as $package)
                            <th scope="col" @class(['rule-b rule-l px-5 py-5 align-bottom', 'bg-paper-sunken' => $package->is_popular])>
                                @if ($package->is_popular)
                                    <x-micro-label class="mb-2 block text-accent">{{ __('site.packages.popular') }}</x-micro-label>
                                @endif

                                <span class="block text-body-lg font-medium text-ink">{{ $package->name }}</span>

                                <span class="mt-2 block text-statement font-medium tabular-nums leading-none text-ink">
                                    {{ $package->price_cents->formatCompact() }}
                                </span>

                                @if ($package->price_is_from)
                                    <x-micro-label class="mt-1 block">{{ __('site.packages.from') }}</x-micro-label>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    {{-- Coverage hours get their own row — it is the thing
                         couples compare first. --}}
                    <tr>
                        <th scope="row" class="rule-b py-4 pr-4 align-top">
                            <span class="text-body-sm text-ink-soft">{{ __('site.packages.coverage') }}</span>
                        </th>
                        @foreach ($packages as $package)
                            <td @class(['rule-b rule-l px-5 py-4 align-top text-body-sm text-ink', 'bg-paper-sunken' => $package->is_popular])>
                                {{ $package->duration_hours ? __('site.packages.hours', ['count' => $package->duration_hours]) : '—' }}
                            </td>
                        @endforeach
                    </tr>

                    @foreach ($allInclusions as $inclusion)
                        <tr>
                            <th scope="row" class="rule-b py-4 pr-4 align-top">
                                <span class="text-body-sm text-ink-soft">{{ $inclusion }}</span>
                            </th>

                            @foreach ($packages as $package)
                                @php $has = in_array($inclusion, $package->inclusions ?? [], true); @endphp
                                <td @class(['rule-b rule-l px-5 py-4 align-top', 'bg-paper-sunken' => $package->is_popular])>
                                    {{-- A visible glyph plus a screen-reader word: a bare
                                         tick/dash is meaningless when announced aloud. --}}
                                    <span aria-hidden="true" class="{{ $has ? 'text-ink' : 'text-ink-faint' }}">
                                        {{ $has ? '●' : '—' }}
                                    </span>
                                    <span class="sr-only">{{ $has ? __('site.packages.included') : __('site.packages.not_included') }}</span>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach

                    <tr>
                        <td class="py-6"></td>
                        @foreach ($packages as $package)
                            <td @class(['rule-l px-5 py-6 align-top', 'bg-paper-sunken' => $package->is_popular])>
                                <a href="{{ route('book', ['package' => $package->slug]) }}"
                                   class="inline-block border border-ink px-4 py-2.5 font-mono text-micro uppercase tracking-micro text-ink transition-colors hover:bg-ink hover:text-paper">
                                    ↗ {{ __('site.packages.choose') }}
                                </a>
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
        </div>
        @endforeach
    </x-section>

    {{-- ---------------------------------------------------------------
         ADD-ONS — each with its own price
         --------------------------------------------------------------- --}}
    <x-section :label="__('site.packages.add_ons')">
        <div class="grid gap-gutter md:grid-cols-12">
            <div class="md:col-span-4">
                <x-reveal>
                    <p class="text-body text-ink-muted">{{ __('site.packages.add_ons_intro') }}</p>
                </x-reveal>
            </div>

            <div class="md:col-span-7 md:col-start-6" data-reveal-group>
                <div class="rule-t">
                    @foreach ($addOns as $addOn)
                        <x-reveal>
                            <div class="rule-b flex items-baseline justify-between gap-6 py-5">
                                <div class="min-w-0">
                                    <h3 class="text-body font-medium text-ink">{{ $addOn->name }}</h3>
                                    @if ($addOn->description)
                                        <p class="mt-1 text-body-sm text-ink-muted">{{ $addOn->description }}</p>
                                    @endif
                                </div>

                                <div class="shrink-0 text-right">
                                    <span class="block text-body font-medium tabular-nums text-ink">
                                        {{ $addOn->price_cents->formatCompact() }}
                                    </span>
                                    @if ($addOn->is_quantifiable)
                                        <x-micro-label class="mt-0.5 block">
                                            {{ __('site.packages.each') }} · {{ __('site.packages.up_to', ['max' => $addOn->max_qty]) }}
                                        </x-micro-label>
                                    @endif
                                </div>
                            </div>
                        </x-reveal>
                    @endforeach
                </div>
            </div>
        </div>
    </x-section>

    {{-- ---------------------------------------------------------------
         THE HONEST NOTE — what changes a quote
         Required by the brief. Stated plainly rather than buried.
         --------------------------------------------------------------- --}}
    <x-section>
        <div class="grid gap-gutter md:grid-cols-12">
            <div class="rule-t pt-6 md:col-span-8 md:col-start-4">
                <x-reveal>
                    <x-micro-label>{{ __('site.packages.honest_note_label') }}</x-micro-label>
                </x-reveal>
                <x-reveal>
                    <p class="mt-4 max-w-measure text-body text-ink-soft">
                        {{ __('site.packages.honest_note') }}
                    </p>
                </x-reveal>
                <x-reveal>
                    <p class="mt-4 max-w-measure text-body-sm text-ink-muted">
                        {{ __('site.packages.deposit_note', ['amount' => $depositPerEvent->formatCompact()]) }}
                    </p>
                </x-reveal>
                <x-reveal>
                    <a href="{{ route('book') }}"
                       class="mt-8 inline-block border border-ink px-6 py-3.5 font-mono text-micro uppercase tracking-micro text-ink transition-colors hover:bg-ink hover:text-paper">
                        ↗ {{ __('site.cta.start_booking') }}
                    </a>
                </x-reveal>
            </div>
        </div>
    </x-section>

</x-layouts.app>

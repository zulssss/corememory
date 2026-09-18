{{--
    HOMEPAGE — Phase 1 shell.

    This renders the real layout, real components and the full motion system,
    but with placeholder content. Phase 2 replaces the hardcoded arrays below
    with Project, Testimonial and Settings models from the database.

    Section order follows the brief exactly.
--}}
<x-layouts.app :title="__('site.brand.name').' — '.__('site.meta.tagline')">

    {{-- ---------------------------------------------------------------
         02. HERO — full bleed, statement headline, corner metadata
         --------------------------------------------------------------- --}}
    <section data-hero class="relative flex h-[88svh] min-h-[32rem] items-end overflow-hidden">

        {{-- Background layer. Scaled so the parallax translate never shows an edge. --}}
        <div data-hero-media class="absolute inset-0 scale-110">
            <x-placeholder-image ratio="16/9" tone="ink" label="Hero" class="h-full w-full" />
        </div>

        {{-- A hairline ring in the top-right corner, per the reference layout. --}}
        <div class="pointer-events-none absolute -right-24 -top-24 hidden h-96 w-96 rounded-full border border-on-inverse/20 md:block" aria-hidden="true"></div>

        <div data-hero-content class="page-gutter relative w-full pb-16">
            <div class="flex justify-end">
                <h1 class="max-w-[22ch] text-right text-display font-medium text-on-inverse">
                    {{ __('site.hero.headline') }}
                </h1>
            </div>
        </div>

        {{-- Corner metadata: location + scroll cue --}}
        <div class="page-gutter pointer-events-none absolute inset-x-0 bottom-5 flex items-end justify-between">
            <x-micro-label tone="inverse">{{ __('site.hero.location') }}</x-micro-label>
            <x-micro-label tone="inverse">({{ __('site.hero.scroll') }})</x-micro-label>
        </div>
    </section>

    {{-- ---------------------------------------------------------------
         03. STUDIO STATEMENT — first clause solid black, remainder grey
         --------------------------------------------------------------- --}}
    <x-section>
        <div class="grid gap-8 md:grid-cols-12 md:gap-gutter">
            <div class="md:col-span-3">
                <x-reveal>
                    <x-micro-label class="max-w-[18ch] leading-relaxed">
                        {{ __('site.statement.eyebrow') }}
                    </x-micro-label>
                </x-reveal>
            </div>

            <div class="md:col-span-9">
                <x-reveal>
                    <p class="text-statement text-ink-muted">
                        <span class="text-ink">{{ __('site.statement.lead') }}</span>
                        {{ __('site.statement.rest') }}
                    </p>
                </x-reveal>
            </div>
        </div>
    </x-section>

    {{-- ---------------------------------------------------------------
         04. BTS MARQUEE — seamless loop, pauses on hover
         The track is duplicated so translating exactly -50% lands on an
         identical frame. Do not remove the duplicate.
         --------------------------------------------------------------- --}}
    <section data-marquee="45" class="overflow-hidden py-6" aria-label="{{ __('site.sections.behind_the_scenes') }}">
        <div data-marquee-track class="flex w-max gap-4">
            @foreach (range(1, 2) as $pass)
                @foreach (range(1, 6) as $i)
                    <div class="h-40 w-64 shrink-0 grayscale md:h-56 md:w-80" @if ($pass === 2) aria-hidden="true" @endif>
                        <x-placeholder-image ratio="3/2" label="BTS {{ $i }}" class="h-full w-full" />
                    </div>
                @endforeach
            @endforeach
        </div>
    </section>

    {{-- ---------------------------------------------------------------
         05. SELECTED WORK — asymmetric grid, intentionally uneven
         --------------------------------------------------------------- --}}
    <x-section
        :label="__('site.sections.selected_work')"
        :link="route('work')"
        :link-label="__('site.cta.see_all_work')"
    >
        {{-- Asymmetry is deliberate: uneven spans, differing ratios, one empty
             cell. It should not resolve into a tidy 3×2 grid. --}}
        <div class="grid grid-cols-1 gap-x-gutter gap-y-12 md:grid-cols-12">
            <x-project-card class="md:col-span-5"  ratio="4/5"  :intensity="0.16" title="Aisyah & Danial"   category="Wedding" />
            <x-project-card class="md:col-span-4"  ratio="3/2"  :intensity="0.10" title="Farah & Hafiz"     category="Nikah" />
            <x-project-card class="md:col-span-3"  ratio="3/2"  :intensity="0.13" title="Mei Ling & Wei"    category="Pre-wedding" />

            {{-- Intentionally empty cell --}}
            <div class="hidden md:col-span-2 md:block" aria-hidden="true"></div>

            <x-project-card class="md:col-span-4"  ratio="3/2"  :intensity="0.11" title="Nurul & Iskandar"  category="Engagement" />
            <x-project-card class="md:col-span-6"  ratio="16/9" :intensity="0.14" title="Priya & Arun"      category="Wedding" />
        </div>
    </x-section>

    {{-- ---------------------------------------------------------------
         06. FEATURED WEDDING — full bleed + metadata row
         --------------------------------------------------------------- --}}
    <section class="py-section">
        <x-reveal type="image" class="block overflow-hidden">
            <x-parallax-image ratio="16/9" :intensity="0.18" label="Featured" class="w-full" />
        </x-reveal>

        <div class="page-gutter mt-6">
            <x-reveal>
                <p class="mb-6 max-w-measure text-body text-ink-soft">
                    {{ __('site.featured.blurb') }}
                </p>
            </x-reveal>

            <x-meta-row :items="[
                __('site.meta_labels.couple') => 'Aisyah & Danial',
                __('site.meta_labels.date')   => '14 Feb 2026',
                __('site.meta_labels.venue')  => 'Kuala Lumpur',
                __('site.meta_labels.crew')   => 'Zul, Amir',
            ]" />
        </div>
    </section>

    {{-- ---------------------------------------------------------------
         07. OUR PACKAGES — prices visible without clicking anything
         --------------------------------------------------------------- --}}
    <x-section
        :label="__('site.sections.our_packages')"
        :link="route('packages')"
        :link-label="__('site.cta.view_packages')"
    >
        <div class="grid gap-gutter md:grid-cols-12">
            <div class="md:col-span-8" data-reveal-group>
                <div class="rule-t">
                    @foreach ([
                        ['Nikah Essentials',  380000, true,  'Half-day coverage, 300 edited photos'],
                        ['Wedding Classic',   680000, false, 'Full-day coverage, two photographers'],
                        ['Wedding Signature', 980000, false, 'Full day, photo + video, same-day edit'],
                        ['Pre-wedding Story', 250000, true,  'Half day, one location'],
                    ] as $i => [$name, $cents, $isFrom, $summary])
                        <x-reveal>
                            <x-package-card
                                :number="$i + 1"
                                :name="$name"
                                :price="new App\ValueObjects\Money($cents)"
                                :price-is-from="$isFrom"
                                :summary="$summary"
                                :popular="$i === 1"
                                :href="route('packages')"
                            />
                        </x-reveal>
                    @endforeach
                </div>
            </div>

            {{-- Small image floating over the list, breaking the grid alignment --}}
            <div class="hidden md:col-span-3 md:col-start-10 md:block">
                <x-reveal type="image" class="mt-16 block overflow-hidden">
                    <x-parallax-image ratio="4/5" :intensity="0.2" label="Studio" />
                </x-reveal>
            </div>
        </div>
    </x-section>

    {{-- ---------------------------------------------------------------
         08. STATS GRID — bordered, some cells deliberately empty
         --------------------------------------------------------------- --}}
    <x-section>
        <div class="grid grid-cols-2 border-l border-t border-rule md:grid-cols-3">
            <x-stat class="rule-b rule-r" :value="5"   :pad="true" :label="__('site.stats.years')" />
            <div class="rule-b rule-r hidden md:block" aria-hidden="true"></div>
            <x-stat class="rule-b rule-r" :value="240" :label="__('site.stats.weddings')" />

            <x-stat class="rule-b rule-r" :value="12"  :pad="true" :label="__('site.stats.awards')" />
            <x-stat class="rule-b rule-r" :value="480" :label="__('site.stats.couples')" />
            <div class="rule-b rule-r hidden md:block" aria-hidden="true"></div>
        </div>
    </x-section>

    {{-- ---------------------------------------------------------------
         09. TESTIMONIALS — scattered at different vertical offsets
         --------------------------------------------------------------- --}}
    <x-section :label="__('site.sections.words_from_couples')">
        <div class="grid gap-gutter md:grid-cols-12">
            <x-reveal class="md:col-span-4">
                <x-testimonial-card
                    context="Wedding, 2026"
                    quote="{{ __('site.testimonials.one') }}"
                    couple="Aisyah & Danial" />
            </x-reveal>

            <x-reveal class="md:col-span-4 md:mt-16">
                <x-testimonial-card
                    context="Nikah, 2025"
                    quote="{{ __('site.testimonials.two') }}"
                    couple="Farah & Hafiz" />
            </x-reveal>

            <x-reveal class="md:col-span-3 md:col-start-10 md:mt-6">
                <x-testimonial-card
                    context="Pre-wedding, 2025"
                    quote="{{ __('site.testimonials.three') }}"
                    couple="Mei Ling & Wei" />
            </x-reveal>
        </div>
    </x-section>

    {{-- ---------------------------------------------------------------
         10. JOURNAL — 3 cards
         --------------------------------------------------------------- --}}
    <x-section
        :label="__('site.sections.stories_behind')"
        :link="route('journal')"
        :link-label="__('site.cta.read_the_journal')"
    >
        <div class="grid gap-gutter md:grid-cols-3" data-reveal-group>
            @foreach ([
                'What to expect on your wedding day',
                'Why we shoot nikah and reception differently',
                'Choosing a venue that photographs well',
            ] as $i => $headline)
                <x-reveal>
                    <x-project-card
                        :title="$headline"
                        :ratio="$i === 1 ? '4/5' : '3/2'"
                        :intensity="0.1 + $i * 0.03"
                        :href="route('journal')" />
                </x-reveal>
            @endforeach
        </div>
    </x-section>

</x-layouts.app>

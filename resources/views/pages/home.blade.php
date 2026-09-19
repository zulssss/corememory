{{--
    HOMEPAGE

    All content comes from the database. Copy is edited in Admin → Site
    settings; projects, testimonials and journal posts in their own resources.

    The whole payload is cached (see HomeController) and busted on admin save.
    Section order follows the brief exactly.
--}}
@php
    use App\Support\Settings;

    // Skip the featured story in the grid so it isn't shown twice.
    $gridProjects = $projects->reject(fn ($p) => $feature && $p->is($feature))->take(5);

    // Ratios and parallax intensities per grid position. Deliberately uneven —
    // the grid should not resolve into a tidy 3x2.
    $layout = [
        ['span' => 'md:col-span-5', 'ratio' => '4/5',  'intensity' => 0.16],
        ['span' => 'md:col-span-4', 'ratio' => '3/2',  'intensity' => 0.10],
        ['span' => 'md:col-span-3', 'ratio' => '3/2',  'intensity' => 0.13],
        ['span' => 'md:col-span-4', 'ratio' => '3/2',  'intensity' => 0.11],
        ['span' => 'md:col-span-6', 'ratio' => '16/9', 'intensity' => 0.14],
    ];
@endphp

<x-layouts.app
    :title="Settings::get('seo.title') ?? __('site.brand.name')"
    :description="Settings::get('seo.description')"
>

    {{-- 02. HERO --------------------------------------------------------- --}}
    <section data-hero class="relative flex h-[88svh] min-h-[32rem] items-end overflow-hidden">
        <div data-hero-media class="absolute inset-0 scale-110">
            <x-responsive-image
                :media="$feature?->getFirstMedia('hero')"
                ratio="16/9"
                :alt="''"
                :eager="true"
                sizes="100vw"
                label="Hero"
                class="h-full w-full" />
        </div>

        {{-- Hairline ring, top-right, per the reference layout --}}
        <div class="pointer-events-none absolute -right-24 -top-24 hidden h-96 w-96 rounded-full border border-on-inverse/20 md:block" aria-hidden="true"></div>

        <div data-hero-content class="page-gutter relative w-full pb-16">
            <div class="flex justify-end">
                <h1 class="max-w-[22ch] text-right text-display font-medium text-on-inverse">
                    {{ Settings::get('hero.headline') }}
                </h1>
            </div>
        </div>

        <div class="page-gutter pointer-events-none absolute inset-x-0 bottom-5 flex items-end justify-between">
            <x-micro-label tone="inverse">{{ Settings::get('hero.location') }}</x-micro-label>
            <x-micro-label tone="inverse">({{ __('site.hero.scroll') }})</x-micro-label>
        </div>
    </section>

    {{-- 03. STUDIO STATEMENT ---------------------------------------------- --}}
    <x-section>
        <div class="grid gap-8 md:grid-cols-12 md:gap-gutter">
            <div class="md:col-span-3">
                <x-reveal>
                    <x-micro-label class="max-w-[18ch] leading-relaxed">
                        {{ Settings::get('statement.eyebrow') }}
                    </x-micro-label>
                </x-reveal>
            </div>

            <div class="md:col-span-9">
                <x-reveal>
                    <p class="text-statement text-ink-muted">
                        <span class="text-ink">{{ Settings::get('statement.lead') }}</span>
                        {{ Settings::get('statement.rest') }}
                    </p>
                </x-reveal>
            </div>
        </div>
    </x-section>

    {{-- 04. BTS MARQUEE ---------------------------------------------------
         The track is duplicated so translating exactly -50% lands on an
         identical frame. Do not remove the duplicate — that's the seam. --}}
    @if ($projects->isNotEmpty())
        <section data-marquee="45" class="overflow-hidden py-6" aria-label="{{ __('site.sections.behind_the_scenes') }}">
            <div data-marquee-track class="flex w-max gap-4">
                @foreach ([1, 2] as $pass)
                    @foreach ($projects as $project)
                        <div class="h-40 w-64 shrink-0 grayscale md:h-56 md:w-80"
                             @if ($pass === 2) aria-hidden="true" @endif>
                            <x-responsive-image
                                :media="$project->getFirstMedia('hero')"
                                ratio="3/2"
                                :alt="$pass === 1 ? __('site.media.alt_bts') : ''"
                                sizes="320px"
                                :label="$project->title" />
                        </div>
                    @endforeach
                @endforeach
            </div>
        </section>
    @endif

    {{-- 05. SELECTED WORK -------------------------------------------------- --}}
    @if ($gridProjects->isNotEmpty())
        <x-section
            :label="__('site.sections.selected_work')"
            :link="route('work')"
            :link-label="__('site.cta.see_all_work')"
        >
            <div class="grid grid-cols-1 gap-x-gutter gap-y-12 md:grid-cols-12">
                @foreach ($gridProjects as $i => $project)
                    @php $cell = $layout[$i] ?? $layout[0]; @endphp

                    <x-project-card
                        :project="$project"
                        :ratio="$cell['ratio']"
                        :intensity="$cell['intensity']"
                        :class="$cell['span']" />

                    {{-- An intentionally empty cell, mid-grid --}}
                    @if ($i === 2)
                        <div class="hidden md:col-span-2 md:block" aria-hidden="true"></div>
                    @endif
                @endforeach
            </div>
        </x-section>
    @endif

    {{-- 06. FEATURED WEDDING + METADATA ROW -------------------------------- --}}
    @if ($feature)
        <section class="py-section">
            <a href="{{ route('work.show', $feature) }}" data-cursor="{{ __('site.cta.view') }}">
                <x-reveal type="image" class="block overflow-hidden">
                    <x-parallax-image
                        ratio="16/9"
                        :intensity="0.18"
                        :media="$feature->getFirstMedia('hero')"
                        :alt="__('site.media.alt_project', ['title' => $feature->title])"
                        sizes="100vw"
                        class="w-full" />
                </x-reveal>
            </a>

            <div class="page-gutter mt-6">
                @if ($feature->excerpt)
                    <x-reveal>
                        <p class="mb-6 max-w-measure text-body text-ink-soft">{{ $feature->excerpt }}</p>
                    </x-reveal>
                @endif

                <x-meta-row :items="$feature->metaRow()" />
            </div>
        </section>
    @endif

    {{-- 07. OUR PACKAGES ---------------------------------------------------
         Phase 3 replaces this with real Package records. Prices must always be
         visible without interaction — that is the premise of the whole site. --}}
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
                                :href="route('packages')" />
                        </x-reveal>
                    @endforeach
                </div>
            </div>

            {{-- Small image floating over the list, breaking the grid alignment --}}
            @if ($projects->isNotEmpty())
                <div class="hidden md:col-span-3 md:col-start-10 md:block">
                    <x-reveal type="image" class="mt-16 block overflow-hidden">
                        <x-parallax-image
                            ratio="4/5"
                            :intensity="0.2"
                            :media="$projects->last()->getFirstMedia('hero')"
                            :alt="''"
                            sizes="25vw" />
                    </x-reveal>
                </div>
            @endif
        </div>
    </x-section>

    {{-- 08. STATS GRID — some cells deliberately empty --------------------- --}}
    <x-section>
        <div class="grid grid-cols-2 border-l border-t border-rule md:grid-cols-3">
            <x-stat class="rule-b rule-r" :value="$stats[0]['value']" :pad="$stats[0]['pad']" :label="$stats[0]['label']" />
            <div class="rule-b rule-r hidden md:block" aria-hidden="true"></div>
            <x-stat class="rule-b rule-r" :value="$stats[1]['value']" :pad="$stats[1]['pad']" :label="$stats[1]['label']" />

            <x-stat class="rule-b rule-r" :value="$stats[2]['value']" :pad="$stats[2]['pad']" :label="$stats[2]['label']" />
            <x-stat class="rule-b rule-r" :value="$stats[3]['value']" :pad="$stats[3]['pad']" :label="$stats[3]['label']" />
            <div class="rule-b rule-r hidden md:block" aria-hidden="true"></div>
        </div>
    </x-section>

    {{-- 09. TESTIMONIALS — scattered at different vertical offsets --------- --}}
    @if ($testimonials->isNotEmpty())
        @php
            // Staggered offsets and spans, applied by position.
            $offsets = [
                'md:col-span-4',
                'md:col-span-4 md:mt-16',
                'md:col-span-3 md:col-start-10 md:mt-6',
            ];
        @endphp

        <x-section :label="__('site.sections.words_from_couples')">
            <div class="grid gap-gutter md:grid-cols-12">
                @foreach ($testimonials as $i => $testimonial)
                    <x-reveal :class="$offsets[$i] ?? 'md:col-span-4'">
                        <x-testimonial-card
                            :context="$testimonial->context"
                            :quote="$testimonial->quote"
                            :couple="$testimonial->couple_name" />
                    </x-reveal>
                @endforeach
            </div>
        </x-section>
    @endif

    {{-- 10. JOURNAL -------------------------------------------------------- --}}
    @if ($posts->isNotEmpty())
        <x-section
            :label="__('site.sections.stories_behind')"
            :link="route('journal')"
            :link-label="__('site.cta.read_the_journal')"
        >
            <div class="grid gap-gutter md:grid-cols-3" data-reveal-group>
                @foreach ($posts as $i => $post)
                    <x-reveal>
                        <x-project-card
                            :title="$post->title"
                            :media="$post->getFirstMedia('cover')"
                            :ratio="$i === 1 ? '4/5' : '3/2'"
                            :intensity="0.1 + $i * 0.03"
                            :href="route('journal')" />
                    </x-reveal>
                @endforeach
            </div>
        </x-section>
    @endif

</x-layouts.app>

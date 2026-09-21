{{--
    /work/{slug} — one wedding story.

    Hero, metadata row, long-form image layout, then next/previous.
--}}
<x-layouts.app
    :title="$project->title.' — '.__('site.brand.name')"
    :description="$project->excerpt"
    og-type="article"
    :schema="[
        App\Support\Seo::project($project),
        App\Support\Seo::breadcrumbs([
            __('site.nav.home') => route('home'),
            __('site.nav.work') => route('work'),
            $project->title => route('work.show', $project),
        ]),
    ]"
>

    {{-- Hero --}}
    <section data-hero class="relative flex h-[70svh] min-h-[26rem] items-end overflow-hidden">
        <div data-hero-media class="absolute inset-0 scale-110">
            <x-responsive-image
                :media="$project->getFirstMedia('hero')"
                ratio="16/9"
                :alt="__('site.media.alt_project', ['title' => $project->title])"
                :eager="true"
                sizes="100vw"
                :label="$project->title"
                class="h-full w-full" />
        </div>

        <div data-hero-content class="page-gutter relative w-full pb-12">
            <x-micro-label tone="inverse">{{ $project->category->label() }}</x-micro-label>
            <h1 class="mt-3 max-w-[16ch] text-display font-medium text-on-inverse">
                {{ $project->title }}
            </h1>
        </div>
    </section>

    {{-- Metadata row --}}
    <x-section size="flush" class="page-gutter pt-8">
        <x-meta-row :items="$project->metaRow()" />
    </x-section>

    {{-- The story --}}
    @if ($project->description)
        <x-section>
            <div class="grid gap-8 md:grid-cols-12">
                <div class="md:col-span-8 md:col-start-4">
                    <x-reveal>
                        <div class="max-w-measure space-y-5 text-body text-ink-soft">
                            @foreach (preg_split('/\n\s*\n/', trim($project->description)) as $paragraph)
                                <p>{{ $paragraph }}</p>
                            @endforeach
                        </div>
                    </x-reveal>
                </div>
            </div>
        </x-section>
    @endif

    {{-- Gallery — a long-form, deliberately uneven image layout --}}
    @if ($gallery->isNotEmpty())
        @php
            // Repeating rhythm: full bleed, then an offset pair, then a narrow
            // column. Keeps a long gallery from reading as a uniform stack.
            $rhythm = [
                ['span' => 'md:col-span-12', 'ratio' => '16/9', 'intensity' => 0.14],
                ['span' => 'md:col-span-7',  'ratio' => '4/5',  'intensity' => 0.16],
                ['span' => 'md:col-span-4 md:col-start-9 md:mt-24', 'ratio' => '3/2', 'intensity' => 0.10],
                ['span' => 'md:col-span-6 md:col-start-2', 'ratio' => '3/2', 'intensity' => 0.12],
                ['span' => 'md:col-span-5', 'ratio' => '4/5', 'intensity' => 0.15],
            ];
        @endphp

        <section class="page-gutter pb-section">
            <div class="grid grid-cols-1 gap-x-gutter gap-y-16 md:grid-cols-12">
                @foreach ($gallery as $i => $media)
                    @php $cell = $rhythm[$i % count($rhythm)]; @endphp

                    <x-reveal type="image" :class="'block overflow-hidden '.$cell['span']">
                        <x-parallax-image
                            :ratio="$cell['ratio']"
                            :intensity="$cell['intensity']"
                            :media="$media"
                            :alt="__('site.media.alt_project', ['title' => $project->title])"
                            sizes="(min-width: 768px) 60vw, 100vw" />
                    </x-reveal>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Testimonial from this couple, if there is one --}}
    @if ($project->testimonials->isNotEmpty())
        <x-section :label="__('site.sections.words_from_couples')">
            <div class="grid gap-gutter md:grid-cols-12">
                @foreach ($project->testimonials->where('is_published', true) as $testimonial)
                    <x-reveal class="md:col-span-6">
                        <x-testimonial-card
                            :context="$testimonial->context"
                            :quote="$testimonial->quote"
                            :couple="$testimonial->couple_name" />
                    </x-reveal>
                @endforeach
            </div>
        </x-section>
    @endif

    {{-- Next / previous --}}
    <x-section size="flush" class="page-gutter rule-t py-10">
        <div class="flex items-center justify-between gap-6">
            @if ($previous)
                <a href="{{ route('work.show', $previous) }}" class="group flex flex-col gap-1">
                    <x-micro-label>{{ __('site.work.previous') }}</x-micro-label>
                    <span class="text-body-lg text-ink transition-colors group-hover:text-accent">{{ $previous->title }}</span>
                </a>
            @else
                <span></span>
            @endif

            @if ($next)
                <a href="{{ route('work.show', $next) }}" class="group flex flex-col gap-1 text-right">
                    <x-micro-label>{{ __('site.work.next') }}</x-micro-label>
                    <span class="text-body-lg text-ink transition-colors group-hover:text-accent">{{ $next->title }}</span>
                </a>
            @endif
        </div>
    </x-section>

</x-layouts.app>

{{-- /about — team, philosophy, the process as a numbered timeline, and gear. --}}
@php use App\Support\Settings; @endphp

<x-layouts.app
    :title="__('site.nav.about').' — '.__('site.brand.name')"
    :description="__('about.meta_description')"
>

    {{-- Statement --}}
    <x-section size="lg" class="pb-0">
        <div class="grid gap-8 md:grid-cols-12">
            <div class="md:col-span-3">
                <x-reveal><x-micro-label>{{ __('site.nav.about') }}</x-micro-label></x-reveal>
            </div>

            <div class="md:col-span-9">
                <x-reveal>
                    <h1 class="text-statement-lg font-medium text-ink">{{ __('about.headline') }}</h1>
                </x-reveal>
            </div>
        </div>
    </x-section>

    {{-- A few frames --}}
    @if ($projects->isNotEmpty())
        <x-section>
            <div class="grid gap-gutter md:grid-cols-12">
                @foreach ($projects as $i => $project)
                    @php
                        $cell = [
                            ['span' => 'md:col-span-5', 'ratio' => '4/5'],
                            ['span' => 'md:col-span-4 md:mt-16', 'ratio' => '3/2'],
                            ['span' => 'md:col-span-3', 'ratio' => '3/2'],
                        ][$i] ?? ['span' => 'md:col-span-4', 'ratio' => '3/2'];
                    @endphp

                    <x-reveal type="image" :class="'block overflow-hidden '.$cell['span']">
                        <x-parallax-image
                            :ratio="$cell['ratio']"
                            :intensity="0.1 + $i * 0.03"
                            :media="$project->getFirstMedia('hero')"
                            :alt="__('site.media.alt_project', ['title' => $project->title])"
                            sizes="(min-width: 768px) 33vw, 100vw" />
                    </x-reveal>
                @endforeach
            </div>
        </x-section>
    @endif

    {{-- Philosophy --}}
    <x-section :label="__('about.philosophy_label')">
        <div class="grid gap-gutter md:grid-cols-12">
            <div class="md:col-span-8 md:col-start-4" data-reveal-group>
                @foreach (__('about.philosophy') as $paragraph)
                    <x-reveal>
                        <p class="mb-5 max-w-measure text-body-lg text-ink-soft">{{ $paragraph }}</p>
                    </x-reveal>
                @endforeach
            </div>
        </div>
    </x-section>

    {{-- Process — numbered 01–06, hairline rules between --}}
    <x-section :label="__('about.process_label')">
        <ol class="rule-t" data-reveal-group>
            @foreach (__('about.process') as $i => $step)
                <x-reveal as="li" class="rule-b flex flex-col gap-2 py-6 md:flex-row md:gap-10">
                    <span class="font-mono text-caption tabular-nums text-ink-faint md:w-12">
                        {{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}
                    </span>

                    <h3 class="text-body-lg font-medium text-ink md:w-64 md:shrink-0">{{ $step['title'] }}</h3>

                    <p class="max-w-measure text-body text-ink-muted">{{ $step['body'] }}</p>
                </x-reveal>
            @endforeach
        </ol>
    </x-section>

    {{-- Gear --}}
    <x-section :label="__('about.gear_label')">
        <div class="grid gap-gutter md:grid-cols-12">
            <div class="md:col-span-4">
                <x-reveal>
                    <p class="text-body-sm text-ink-muted">{{ __('about.gear_note') }}</p>
                </x-reveal>
            </div>

            <div class="md:col-span-7 md:col-start-6" data-reveal-group>
                <ul class="rule-t">
                    @foreach (__('about.gear') as $item)
                        <x-reveal as="li" class="rule-b py-4 text-body text-ink-soft">{{ $item }}</x-reveal>
                    @endforeach
                </ul>
            </div>
        </div>
    </x-section>

    {{-- CTA --}}
    <x-section>
        <div class="grid gap-gutter md:grid-cols-12">
            <div class="rule-t pt-6 md:col-span-8 md:col-start-4">
                <x-reveal><x-micro-label>{{ __('about.cta_label') }}</x-micro-label></x-reveal>
                <x-reveal>
                    <p class="mt-4 max-w-measure text-body-lg text-ink-soft">{{ __('about.cta_body') }}</p>
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

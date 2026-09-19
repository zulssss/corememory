{{--
    A wedding story in a portfolio grid: parallax image, caption beneath.

    Pass a Project model and everything is derived from it:
        <x-project-card :project="$project" ratio="4/5" :intensity="0.16" />

    Or pass plain props for non-project cards (e.g. journal entries).
--}}
@props([
    'project' => null,
    'title' => null,
    'category' => null,
    'href' => null,
    'ratio' => '3/2',
    'intensity' => 0.12,
    'media' => null,
    'eager' => false,
])

@php
    $title ??= $project?->title;
    $category ??= $project?->category?->label();
    $href ??= $project ? route('work.show', $project) : '#';
    $media ??= $project?->getFirstMedia('hero');
@endphp

<article {{ $attributes->class('group') }}>
    <a href="{{ $href }}"
       data-cursor="{{ __('site.cta.view') }}"
       class="block focus-visible:outline-offset-4">

        <x-reveal type="image" class="block overflow-hidden">
            <x-parallax-image
                :ratio="$ratio"
                :intensity="$intensity"
                :media="$media"
                :label="$title"
                :alt="$title ? __('site.media.alt_project', ['title' => $title]) : ''"
                :eager="$eager"
                sizes="(min-width: 768px) 33vw, 100vw" />
        </x-reveal>

        <div class="mt-3 flex items-baseline justify-between gap-4">
            <x-micro-label class="transition-colors group-hover:text-ink">
                {{ $title }}
            </x-micro-label>

            @if ($category)
                <x-micro-label class="shrink-0 text-ink-faint">{{ $category }}</x-micro-label>
            @endif
        </div>
    </a>
</article>

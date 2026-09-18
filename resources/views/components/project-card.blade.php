{{--
    A wedding story in the SELECTED WORK grid: parallax image, caption label
    beneath.

    Phase 1 renders placeholders. Phase 2 passes a real Project model.
--}}
@props([
    'title' => null,
    'category' => null,
    'href' => null,
    'ratio' => '3/2',
    'intensity' => 0.12,
    'src' => null,
])

<article {{ $attributes->class('group') }}>
    <a href="{{ $href ?? '#' }}"
       data-cursor="{{ __('site.cta.view') }}"
       class="block focus-visible:outline-offset-4">

        <x-reveal type="image" class="block overflow-hidden">
            <x-parallax-image :ratio="$ratio" :intensity="$intensity" :label="$title" :src="$src" :alt="$title ?? ''" />
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

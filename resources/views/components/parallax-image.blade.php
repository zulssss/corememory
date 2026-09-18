{{--
    An image that translates inside its own masked container as the page
    scrolls.

    The container clips (overflow-hidden, set in app.css) and the child is
    scaled slightly beyond 100% so the translation never exposes an edge.
    Only `transform` animates — never height or top.

    `intensity` varies the travel so a grid of these doesn't move as one flat
    plane. Parallax is dropped entirely under 768px and under reduced motion.

    Usage:
        <x-parallax-image ratio="3/2" intensity="0.18" label="Aisyah & Danial" />
--}}
@props([
    'ratio' => '3/2',
    'intensity' => 0.12,
    'tone' => 'sunken',
    'label' => null,
    'src' => null,
    'alt' => '',
])

<div
    data-parallax="{{ $intensity }}"
    {{ $attributes->class('relative') }}
    style="aspect-ratio: {{ str_replace('/', ' / ', $ratio) }}"
>
    {{-- scale-110 gives the transform room to travel without revealing a gap --}}
    <div class="h-full w-full scale-110">
        @if ($src)
            <img src="{{ $src }}" alt="{{ $alt }}" loading="lazy" decoding="async"
                 class="h-full w-full object-cover">
        @else
            <x-placeholder-image :ratio="$ratio" :tone="$tone" :label="$label" />
        @endif
    </div>
</div>

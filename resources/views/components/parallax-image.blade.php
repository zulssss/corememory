{{--
    An image that translates inside its own masked container as the page
    scrolls.

    The container clips (overflow-hidden, set in app.css) and the child is
    scaled slightly beyond 100% so the translation never exposes an edge.
    Only `transform` animates — never height or top.

    `intensity` varies the travel so a grid of these doesn't move as one flat
    plane. Parallax is dropped entirely under 768px and under reduced motion.
--}}
@props([
    'ratio' => '3/2',
    'intensity' => 0.12,
    'tone' => 'sunken',
    'label' => null,
    'media' => null,
    'alt' => '',
    'eager' => false,
    'sizes' => '(min-width: 768px) 50vw, 100vw',
])

<div
    data-parallax="{{ $intensity }}"
    {{ $attributes->class('relative') }}
    style="aspect-ratio: {{ str_replace('/', ' / ', $ratio) }}"
>
    {{-- scale-110 gives the transform room to travel without revealing a gap --}}
    <div class="h-full w-full scale-110">
        @if ($media)
            <x-responsive-image :media="$media" :ratio="$ratio" :alt="$alt" :eager="$eager" :sizes="$sizes" />
        @else
            <x-placeholder-image :ratio="$ratio" :tone="$tone" :label="$label" />
        @endif
    </div>
</div>

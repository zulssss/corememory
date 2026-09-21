{{--
    Renders a media-library image with a srcset, lazy loading below the fold,
    and explicit dimensions.

    The dimensions matter: without width/height the browser doesn't know how
    tall the image will be, so the page jumps as each one loads. That's
    Cumulative Layout Shift, and it's the easiest way to fail the Lighthouse
    target.

    Falls back to a placeholder SVG when there is no media, so a project with
    no images yet still lays out correctly instead of collapsing.
--}}
@props([
    'media' => null,
    'ratio' => '3/2',
    'alt' => '',
    'eager' => false,       // true for above-the-fold images only
    'sizes' => '(min-width: 768px) 50vw, 100vw',
    'label' => null,
])

@php
    use App\Support\Lqip;

    [$rw, $rh] = array_map('intval', explode('/', $ratio));

    // The blurred placeholder sits behind the image as a background, so there
    // is never an empty grey box — the photograph fades in over a blurred
    // version of itself.
    $placeholder = Lqip::dataUri($media);
    // Nominal intrinsic size at the right aspect ratio. The browser only needs
    // the RATIO to reserve the box correctly, not the true pixel dimensions.
    $width = 1200;
    $height = (int) round($width * $rh / $rw);
@endphp

@if ($media)
    <img
        src="{{ $media->hasGeneratedConversion('grid') ? $media->getUrl('grid') : $media->getUrl() }}"
        @if ($media->hasGeneratedConversion('grid') && $media->hasGeneratedConversion('full'))
            srcset="{{ $media->getUrl('grid') }} 900w, {{ $media->getUrl('full') }} 1800w"
            sizes="{{ $sizes }}"
        @endif
        alt="{{ $alt }}"
        width="{{ $width }}"
        height="{{ $height }}"
        loading="{{ $eager ? 'eager' : 'lazy' }}"
        {{ $eager ? 'fetchpriority=high' : '' }}
        decoding="async"
        @if ($placeholder)
            style="background-image: url('{{ $placeholder }}'); background-size: cover; background-position: center;"
        @endif
        {{ $attributes->class('h-full w-full object-cover') }}
    >
@else
    <x-placeholder-image :ratio="$ratio" :label="$label ?? $alt" {{ $attributes }} />
@endif

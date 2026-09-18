{{--
    The tiny uppercase wide-tracked grey label that carries most of this site's
    structure: SELECTED WORK, OUR PACKAGES, COUPLE, DATE, VENUE, CREW.

    Usage:  <x-micro-label>Selected Work</x-micro-label>
            <x-micro-label as="h2" tone="ink">Our Packages</x-micro-label>
--}}
@props([
    'as' => 'p',
    'tone' => 'muted', // muted | ink | inverse
])

@php
    $toneClass = match ($tone) {
        'ink' => 'text-ink',
        'inverse' => 'text-on-inverse-muted',
        default => 'text-ink-muted',
    };
@endphp

<{{ $as }} {{ $attributes->class(['micro-label', $toneClass]) }}>{{ $slot }}</{{ $as }}>

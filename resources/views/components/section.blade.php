{{--
    A page section with the standard vertical rhythm and optional heading row.

    Usage:
        <x-section label="Selected Work" :link="route('work')" link-label="See all work">
            ...
        </x-section>
--}}
@props([
    'label' => null,
    'link' => null,
    'linkLabel' => null,
    'size' => 'base',     // base | lg | intro | flush
    'gutter' => true,
    'ruleTop' => false,
])

@php
    $padding = match ($size) {
        'lg' => 'py-section-lg',
        // An inner page's opening section: the generous bottom rhythm of lg,
        // but only a normal gap under the header. pt-* overrides the top half
        // of py-*, the same way the existing `pb-0` overrides the bottom.
        'intro' => 'py-section-lg pt-page-top',
        'flush' => '',
        default => 'py-section',
    };
@endphp

<section {{ $attributes->class([
    $padding,
    'page-gutter' => $gutter,
    'rule-t' => $ruleTop,
]) }}>
    @if ($label || $link)
        <div class="mb-10 flex items-baseline justify-between gap-6">
            @if ($label)
                <x-micro-label as="h2">{{ $label }}</x-micro-label>
            @endif

            @if ($link)
                <x-arrow-link :href="$link">{{ $linkLabel ?? __('site.cta.see_all') }}</x-arrow-link>
            @endif
        </div>
    @endif

    {{ $slot }}
</section>

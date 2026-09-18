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
    'size' => 'base',     // base | lg | flush
    'gutter' => true,
    'ruleTop' => false,
])

@php
    $padding = match ($size) {
        'lg' => 'py-section-lg',
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

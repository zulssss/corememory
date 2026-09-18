{{--
    Neutral placeholder image at a correct aspect ratio.

    Renders an inline SVG rather than hotlinking anything. Real photography is
    swapped in during Phase 2 via the media library; nothing here is ever a
    remote asset, and no content is invented.

    Explicit width/height are required — they reserve the box before the image
    paints, which is what keeps Cumulative Layout Shift at zero.
--}}
@props([
    'ratio' => '3/2',        // 3/2 | 2/3 | 1/1 | 16/9 | 4/5
    'tone' => 'sunken',      // sunken | raised | ink
    'label' => null,
])

@php
    [$w, $h] = array_map('intval', explode('/', $ratio));

    $fill = match ($tone) {
        'raised' => 'var(--color-paper-raised)',
        'ink' => 'var(--color-inverse-raised)',
        default => 'var(--color-paper-sunken)',
    };

    $labelFill = $tone === 'ink' ? 'var(--color-on-inverse-muted)' : 'var(--color-ink-faint)';
@endphp

<svg
    {{ $attributes->class('block h-full w-full object-cover') }}
    viewBox="0 0 {{ $w * 100 }} {{ $h * 100 }}"
    width="{{ $w * 100 }}"
    height="{{ $h * 100 }}"
    preserveAspectRatio="xMidYMid slice"
    role="img"
    aria-label="{{ $label ?? __('site.media.placeholder') }}"
>
    <rect width="100%" height="100%" fill="{{ $fill }}" />
    <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle"
          font-family="var(--font-mono)" font-size="{{ max($w, $h) * 5 }}"
          letter-spacing="6" fill="{{ $labelFill }}">
        {{ Str::upper($label ?? __('site.media.placeholder')) }}
    </text>
</svg>

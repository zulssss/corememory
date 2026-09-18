{{--
    A hairline divider that draws itself in from left to right on enter.
    Animates scaleX (a transform), never width — width would trigger layout.
--}}
@props(['tone' => 'default'])

<div
    data-reveal="line"
    {{ $attributes->class([
        'h-px w-full origin-left',
        'bg-rule' => $tone === 'default',
        'bg-inverse-rule' => $tone === 'inverse',
    ]) }}
    role="presentation"
></div>

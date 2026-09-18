{{--
    Wraps content so it fades and rises into view once on enter.

    The animation itself lives in motion.js — this component only marks the
    element. Under prefers-reduced-motion, or if JS fails, the content renders
    in its final state (see app.css).

    Usage:
        <x-reveal>...</x-reveal>            text fade + rise
        <x-reveal type="image">...</x-reveal>  clip-reveal for media
        <x-reveal type="line" />               a divider rule that draws in
        <x-reveal group>...</x-reveal>         children stagger as a batch
--}}
@props([
    'type' => 'up',   // up | image | line
    'as' => 'div',
    'group' => false,
])

<{{ $as }}
    data-reveal="{{ $type }}"
    @if ($group) data-reveal-group @endif
    {{ $attributes }}
>{{ $slot }}</{{ $as }}>

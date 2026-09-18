{{--
    Section link with the ↗ prefix used throughout the reference layout.
    The arrow is aria-hidden so screen readers get the label, not "north east
    arrow".
--}}
@props(['href' => '#'])

<a href="{{ $href }}" {{ $attributes->class('micro-label inline-flex items-center gap-1.5 transition-colors hover:text-ink') }}>
    <span aria-hidden="true">↗</span>
    <span>{{ $slot }}</span>
</a>

{{--
    A testimonial with small L-shaped corner crop marks (drawn in CSS via
    .crop-marks, so no extra DOM).

    Cards are placed at staggered vertical offsets by the parent grid, not by
    this component — offset is a layout concern.
--}}
@props([
    'quote' => '',
    'couple' => null,
    'context' => null,
])

<figure {{ $attributes->class('crop-marks bg-paper-raised p-6 md:p-8') }}>
    @if ($context)
        <x-micro-label class="mb-4 block text-ink-faint">{{ $context }}</x-micro-label>
    @endif

    <blockquote class="text-body-sm leading-relaxed text-ink-soft">
        {{ $quote }}
    </blockquote>

    @if ($couple)
        <figcaption class="micro-label mt-5">— {{ $couple }}</figcaption>
    @endif
</figure>

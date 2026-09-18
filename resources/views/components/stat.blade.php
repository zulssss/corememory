{{--
    One cell of the stats grid: a big numeral that counts up on enter, with a
    tight two-line micro label beside it.

    `data-count-to` is what motion.js animates. The final value is also the
    rendered text, so with JS off or reduced motion on, the real number is
    already there.
--}}
@props([
    'value' => 0,
    'label' => '',
    'pad' => false,   // render as 05 rather than 5
])

@php
    $display = $pad ? str_pad((string) $value, 2, '0', STR_PAD_LEFT) : (string) $value;
@endphp

<div {{ $attributes->class('flex items-start gap-4 p-6 md:p-8') }}>
    <span data-count-to="{{ $value }}"
          class="text-numeral font-medium tabular-nums text-ink">{{ $display }}</span>
    <span class="micro-label max-w-[7rem] pt-2 leading-snug">{{ $label }}</span>
</div>

{{--
    The COUPLE / DATE / VENUE / CREW row that sits under a featured wedding,
    separated from the image by a hairline rule.

    Pass an associative array of label => value.
--}}
@props(['items' => []])

<div {{ $attributes->class('rule-t grid grid-cols-2 gap-6 pt-5 md:grid-cols-4 md:gap-8') }}>
    @foreach ($items as $label => $value)
        <div class="flex flex-col gap-1.5">
            <x-micro-label>{{ $label }}</x-micro-label>
            <span class="text-body-sm text-ink">{{ $value }}</span>
        </div>
    @endforeach
</div>

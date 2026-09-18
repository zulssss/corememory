{{--
    A package as a numbered row: number, name, inclusions, price locked to the
    right edge.

    The point of this component is that the PRICE IS ALWAYS VISIBLE without any
    interaction — that is the whole premise of the site. Never hide it behind a
    click, a hover, or an "enquire for pricing".

    Phase 1 renders from plain props; Phase 3 passes a Package model.
--}}
@props([
    'number' => null,
    'name' => '',
    'price' => null,          // an App\ValueObjects\Money
    'priceIsFrom' => false,
    'summary' => null,
    'popular' => false,
    'href' => null,
])

<article {{ $attributes->class('rule-b group relative') }}>
    <a href="{{ $href ?? '#' }}" class="flex items-baseline gap-5 py-6 md:gap-10">

        @if ($number)
            <span class="font-mono text-caption tabular-nums text-ink-faint">
                {{ str_pad((string) $number, 2, '0', STR_PAD_LEFT) }}
            </span>
        @endif

        <div class="min-w-0 flex-1">
            <h3 class="text-body-lg font-medium text-ink transition-colors group-hover:text-accent">
                {{ $name }}
                @if ($popular)
                    <span class="ml-2 align-middle font-mono text-micro uppercase tracking-micro text-accent">
                        {{ __('site.packages.popular') }}
                    </span>
                @endif
            </h3>

            @if ($summary)
                <p class="mt-1 text-body-sm text-ink-muted">{{ $summary }}</p>
            @endif
        </div>

        {{-- Price, right-aligned. Never hidden. --}}
        <div class="shrink-0 text-right">
            @if ($priceIsFrom)
                <span class="block font-mono text-micro uppercase tracking-micro text-ink-muted">
                    {{ __('site.packages.from') }}
                </span>
            @endif
            <span class="text-body-lg font-medium tabular-nums text-ink">
                {{ $price?->formatCompact() ?? __('site.packages.on_request') }}
            </span>
        </div>
    </a>
</article>

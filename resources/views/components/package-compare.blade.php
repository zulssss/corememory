@props([
    /** @var \Illuminate\Support\Collection<int, \App\Models\Package> */
    'packages',
    'category',            // App\Enums\PackageCategory — the active tab
    'selectedId' => null,
])

{{--
    A compact comparison of one category's packages, for the booking wizard.

    The same idea as the /packages comparison table, scaled to fit the wizard's
    column: one category at a time, so a couple compares four (or six) packages
    side by side instead of scrolling eighteen stacked cards and forgetting the
    first by the time they reach the last.

    The Choose buttons sit in the column headers, so picking never needs a
    scroll. The selected column is highlighted top to bottom.
--}}
@php
    use Illuminate\Support\Str;

    // Rows: every inclusion in this category, in order of first appearance,
    // so the ● and — line up across columns.
    $inclusions = $packages->flatMap(fn ($p) => $p->inclusions ?? [])->unique()->values();

    // Inside the "Photo" tab, "Photo Basic — Single Event" reads "Basic —
    // Single Event": the tab already says Photo. Str::after returns the whole
    // name when there is no such prefix (Sessions), which is what we want.
    $shortName = fn ($p) => Str::after($p->name, $category->label().' ');

    $isSelected = fn ($p) => $selectedId === $p->id;
@endphp

{{-- Keyed by category: switching tabs REPLACES the table instead of letting
     Livewire's morph patch each Choose button's wire:click from one package id
     to another — the patch that left the submit button bound to nothing. --}}
<div class="overflow-x-auto" wire:key="package-compare-{{ $category->value }}">
    <table class="w-full min-w-[34rem] border-collapse text-left">
        <caption class="sr-only">{{ __('site.packages.compare') }} — {{ $category->label() }}</caption>

        <thead>
            <tr>
                {{-- Sticky so the row labels stay readable when six columns scroll. --}}
                <th scope="col" class="sticky left-0 z-10 w-40 bg-paper align-bottom pb-3 pr-3">
                    <x-micro-label>{{ __('site.packages.whats_included') }}</x-micro-label>
                </th>

                @foreach ($packages as $package)
                    <th scope="col" @class([
                        'border-t-2 px-3 pt-3 pb-3 align-bottom',
                        'border-ink bg-paper-raised' => $isSelected($package),
                        'border-transparent' => ! $isSelected($package),
                    ])>
                        @if ($package->is_popular)
                            <span class="mb-1 block font-mono text-micro uppercase tracking-micro text-accent">{{ __('site.packages.popular') }}</span>
                        @endif

                        <span class="block text-body-sm font-medium leading-snug text-ink">{{ $shortName($package) }}</span>
                        <span class="mt-1 block text-body-lg font-medium tabular-nums leading-none text-ink">{{ $package->displayPrice() }}</span>

                        <button
                            type="button"
                            wire:key="package-choose-{{ $package->id }}"
                            wire:click="selectPackage({{ $package->id }})"
                            aria-pressed="{{ $isSelected($package) ? 'true' : 'false' }}"
                            aria-label="{{ $package->name }}"
                            @class([
                                'mt-3 w-full border px-2 py-1.5 font-mono text-micro uppercase tracking-micro transition-colors',
                                'border-ink bg-ink text-paper' => $isSelected($package),
                                'border-ink text-ink hover:bg-ink hover:text-paper' => ! $isSelected($package),
                            ])
                        >
                            {{ $isSelected($package) ? '● '.__('booking.wizard.selected') : __('booking.wizard.choose') }}
                        </button>
                    </th>
                @endforeach
            </tr>
        </thead>

        <tbody>
            {{-- Coverage first: it's what couples compare before anything else. --}}
            <tr>
                <th scope="row" class="sticky left-0 z-10 rule-t bg-paper py-2 pr-3 text-left align-top text-body-sm font-normal text-ink-soft">
                    {{ __('site.packages.coverage') }}
                </th>
                @foreach ($packages as $package)
                    <td @class(['rule-t px-3 py-2 align-top text-body-sm text-ink', 'bg-paper-raised' => $isSelected($package)])>
                        {{ $package->duration_hours ? __('site.packages.hours', ['count' => $package->duration_hours]) : '—' }}
                    </td>
                @endforeach
            </tr>

            @foreach ($inclusions as $inclusion)
                <tr>
                    <th scope="row" class="sticky left-0 z-10 rule-t bg-paper py-2 pr-3 text-left align-top text-body-sm font-normal text-ink-soft">
                        {{ $inclusion }}
                    </th>
                    @foreach ($packages as $package)
                        @php $has = in_array($inclusion, $package->inclusions ?? [], true); @endphp
                        <td @class(['rule-t px-3 py-2 align-top', 'bg-paper-raised' => $isSelected($package)])>
                            {{-- A glyph for the eye, a word for screen readers. --}}
                            <span aria-hidden="true" class="{{ $has ? 'text-ink' : 'text-ink-faint' }}">{{ $has ? '●' : '—' }}</span>
                            <span class="sr-only">{{ $has ? __('site.packages.included') : __('site.packages.not_included') }}</span>
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

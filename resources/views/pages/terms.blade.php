{{--
    /terms — the studio's booking terms, published openly.

    Deliberately plain: numbered clauses on a readable measure, no imagery, no
    motion beyond the standard reveal. This is the page a couple reads before
    parting with a deposit, so legibility beats art direction.
--}}
<x-layouts.app
    :title="__('terms.meta_title').' — '.__('site.brand.name')"
    :description="__('terms.meta_description')"
>

    <x-section size="intro" class="pb-0">
        <div class="grid gap-8 md:grid-cols-12">
            <div class="md:col-span-3">
                <x-reveal><x-micro-label>{{ __('terms.meta_title') }}</x-micro-label></x-reveal>
            </div>

            <div class="md:col-span-9">
                <x-reveal>
                    <h1 class="text-statement-lg font-medium text-ink">{{ __('terms.headline') }}</h1>
                </x-reveal>

                <x-reveal>
                    <p class="mt-6 max-w-measure text-body-lg text-ink-soft">{{ __('terms.intro') }}</p>
                </x-reveal>
            </div>
        </div>
    </x-section>

    <x-section>
        <div class="grid gap-gutter md:grid-cols-12">
            {{-- A running count across every section, so a clause can be
                 referred to by number in an email or over the phone. --}}
            @php $clause = 0; @endphp

            @foreach (['booking', 'coverage', 'delivery', 'cancellation'] as $group)
                <div class="md:col-span-3">
                    <x-reveal>
                        <h2 class="rule-t pt-4 font-mono text-micro uppercase tracking-micro text-ink-muted">
                            {{ __('terms.'.$group.'_heading') }}
                        </h2>
                    </x-reveal>
                </div>

                <div class="md:col-span-9 md:mb-10">
                    <ol class="flex flex-col">
                        @foreach (__('terms.'.$group) as $item)
                            @php $clause++; @endphp
                            <li class="rule-t flex gap-5 py-4 first:border-t-0 first:pt-0 md:first:border-t md:first:pt-4">
                                <span class="shrink-0 font-mono text-micro tabular-nums text-ink-faint">
                                    {{ str_pad((string) $clause, 2, '0', STR_PAD_LEFT) }}
                                </span>
                                <span class="max-w-measure text-body text-ink-soft">{{ $item }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endforeach
        </div>
    </x-section>

    <x-section size="sm">
        <div class="grid gap-8 md:grid-cols-12">
            <div class="md:col-span-9 md:col-start-4">
                <x-reveal>
                    <p class="max-w-measure text-body-lg text-ink">{{ __('terms.questions') }}</p>
                </x-reveal>

                <x-reveal>
                    <a href="{{ route('contact') }}"
                       class="mt-6 inline-block border border-ink px-6 py-3.5 font-mono text-micro uppercase tracking-micro text-ink transition-colors hover:bg-ink hover:text-paper">
                        {{ __('site.nav.contact') }} →
                    </a>
                </x-reveal>
            </div>
        </div>
    </x-section>

</x-layouts.app>

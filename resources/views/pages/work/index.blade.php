{{--
    /work — the portfolio index, filterable by category.

    Filters are plain links with a query string, not JavaScript: they work
    without JS, they're shareable, and each filtered view is its own URL that
    search engines can index.
--}}
@php use App\Enums\ProjectCategory; @endphp

<x-layouts.app
    :title="__('site.nav.work').' — '.__('site.brand.name')"
    :description="__('site.work.meta_description')"
>

    <x-section size="intro" class="pb-0">
        <x-reveal>
            <x-micro-label>{{ __('site.sections.selected_work') }}</x-micro-label>
        </x-reveal>

        <x-reveal>
            <h1 class="mt-4 max-w-[16ch] text-statement-lg font-medium text-ink">
                {{ __('site.work.headline') }}
            </h1>
        </x-reveal>

        {{-- Category filters --}}
        <nav class="rule-t mt-12 flex flex-wrap gap-x-8 gap-y-3 pt-5" aria-label="{{ __('site.work.filter_label') }}">
            <a href="{{ route('work') }}"
               @class(['micro-label transition-colors hover:text-ink', 'text-ink' => ! $activeCategory])
               @if (! $activeCategory) aria-current="page" @endif>
                {{ __('site.work.all') }}
            </a>

            @foreach (ProjectCategory::cases() as $category)
                <a href="{{ route('work', ['category' => $category->value]) }}"
                   @class([
                       'micro-label transition-colors hover:text-ink',
                       'text-ink' => $activeCategory === $category,
                   ])
                   @if ($activeCategory === $category) aria-current="page" @endif>
                    {{ $category->label() }}
                </a>
            @endforeach
        </nav>
    </x-section>

    <x-section>
        @if ($projects->isEmpty())
            <p class="text-body text-ink-muted">{{ __('site.work.empty') }}</p>
        @else
            {{-- Alternating spans keep the grid from reading as a plain 3-column
                 repeat while staying predictable across any number of items. --}}
            @php
                $cells = [
                    ['span' => 'md:col-span-5', 'ratio' => '4/5',  'intensity' => 0.16],
                    ['span' => 'md:col-span-4', 'ratio' => '3/2',  'intensity' => 0.10],
                    ['span' => 'md:col-span-3', 'ratio' => '3/2',  'intensity' => 0.13],
                    ['span' => 'md:col-span-4', 'ratio' => '3/2',  'intensity' => 0.11],
                    ['span' => 'md:col-span-5', 'ratio' => '16/9', 'intensity' => 0.14],
                    ['span' => 'md:col-span-3', 'ratio' => '4/5',  'intensity' => 0.12],
                ];
            @endphp

            <div class="grid grid-cols-1 gap-x-gutter gap-y-12 md:grid-cols-12">
                @foreach ($projects as $i => $project)
                    @php $cell = $cells[$i % count($cells)]; @endphp

                    <x-project-card
                        :project="$project"
                        :ratio="$cell['ratio']"
                        :intensity="$cell['intensity']"
                        :eager="$i < 2"
                        :class="$cell['span']" />
                @endforeach
            </div>

            @if ($projects->hasPages())
                <div class="rule-t mt-16 pt-6">
                    {{ $projects->links() }}
                </div>
            @endif
        @endif
    </x-section>

</x-layouts.app>

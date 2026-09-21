{{-- /journal — a simple list of published posts. --}}
<x-layouts.app
    :title="__('site.nav.journal').' — '.__('site.brand.name')"
    :description="__('journal.meta_description')"
>

    <x-section size="lg" class="pb-0">
        <x-reveal><x-micro-label>{{ __('site.nav.journal') }}</x-micro-label></x-reveal>
        <x-reveal>
            <h1 class="mt-4 max-w-[18ch] text-statement-lg font-medium text-ink">{{ __('journal.headline') }}</h1>
        </x-reveal>
    </x-section>

    <x-section>
        @if ($posts->isEmpty())
            <p class="text-body text-ink-muted">{{ __('journal.empty') }}</p>
        @else
            <div class="grid gap-x-gutter gap-y-14 md:grid-cols-12">
                @foreach ($posts as $i => $post)
                    @php
                        $cell = [
                            ['span' => 'md:col-span-7', 'ratio' => '16/9'],
                            ['span' => 'md:col-span-4 md:col-start-9 md:mt-16', 'ratio' => '4/5'],
                            ['span' => 'md:col-span-5', 'ratio' => '3/2'],
                            ['span' => 'md:col-span-6 md:col-start-7', 'ratio' => '3/2'],
                        ][$i % 4];
                    @endphp

                    <article class="group {{ $cell['span'] }}">
                        <a href="{{ route('journal.show', $post) }}" data-cursor="{{ __('site.cta.view') }}" class="block">
                            <x-reveal type="image" class="block overflow-hidden">
                                <x-parallax-image
                                    :ratio="$cell['ratio']"
                                    :intensity="0.1 + ($i % 4) * 0.02"
                                    :media="$post->getFirstMedia('cover')"
                                    :alt="$post->title"
                                    :eager="$i === 0"
                                    :label="$post->title"
                                    sizes="(min-width: 768px) 50vw, 100vw" />
                            </x-reveal>

                            <x-micro-label class="mt-4 block">
                                {{ $post->published_at?->translatedFormat('d M Y') }}
                            </x-micro-label>

                            <h2 class="mt-2 max-w-[28ch] text-statement font-medium text-ink transition-colors group-hover:text-accent">
                                {{ $post->title }}
                            </h2>

                            @if ($post->excerpt)
                                <p class="mt-3 max-w-measure text-body text-ink-muted">{{ $post->excerpt }}</p>
                            @endif
                        </a>
                    </article>
                @endforeach
            </div>

            @if ($posts->hasPages())
                <div class="rule-t mt-16 pt-6">{{ $posts->links() }}</div>
            @endif
        @endif
    </x-section>

</x-layouts.app>

{{-- /journal/{slug} — one article. --}}
<x-layouts.app
    :title="$post->title.' — '.__('site.brand.name')"
    :description="$post->excerpt"
    og-type="article"
    :schema="[
        App\Support\Seo::article($post),
        App\Support\Seo::breadcrumbs([
            __('site.nav.home') => route('home'),
            __('site.nav.journal') => route('journal'),
            $post->title => route('journal.show', $post),
        ]),
    ]"
>

    <x-section size="intro" class="pb-0">
        <div class="grid gap-8 md:grid-cols-12">
            <div class="md:col-span-8 md:col-start-3">
                <x-reveal>
                    <x-arrow-link :href="route('journal')">{{ __('journal.back') }}</x-arrow-link>
                </x-reveal>

                <x-reveal>
                    <h1 class="mt-6 text-statement-lg font-medium text-ink">{{ $post->title }}</h1>
                </x-reveal>

                <x-reveal>
                    <x-micro-label class="mt-4 block">
                        {{ __('journal.published') }}
                        <time datetime="{{ $post->published_at?->toDateString() }}">
                            {{ $post->published_at?->translatedFormat('d M Y') }}
                        </time>
                    </x-micro-label>
                </x-reveal>
            </div>
        </div>
    </x-section>

    @if ($post->getFirstMedia('cover'))
        <x-section size="flush" class="py-12">
            <x-reveal type="image" class="block overflow-hidden">
                <x-parallax-image
                    ratio="16/9"
                    :intensity="0.16"
                    :media="$post->getFirstMedia('cover')"
                    :alt="$post->title"
                    :eager="true"
                    sizes="100vw" />
            </x-reveal>
        </x-section>
    @endif

    <x-section size="flush" class="page-gutter pb-section">
        <div class="grid gap-8 md:grid-cols-12">
            <div class="md:col-span-8 md:col-start-3">
                <x-reveal>
                    <div class="max-w-measure space-y-5 text-body-lg text-ink-soft">
                        @foreach (preg_split('/\n\s*\n/', trim((string) $post->body)) as $paragraph)
                            <p>{{ $paragraph }}</p>
                        @endforeach
                    </div>
                </x-reveal>
            </div>
        </div>
    </x-section>

    @if ($more->isNotEmpty())
        <x-section :label="__('journal.more')" rule-top>
            <div class="grid gap-gutter md:grid-cols-2" data-reveal-group>
                @foreach ($more as $other)
                    <x-reveal>
                        <x-project-card
                            :title="$other->title"
                            :media="$other->getFirstMedia('cover')"
                            ratio="3/2"
                            :href="route('journal.show', $other)" />
                    </x-reveal>
                @endforeach
            </div>
        </x-section>
    @endif

</x-layouts.app>

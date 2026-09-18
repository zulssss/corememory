{{--
    Temporary page for routes that exist in the navigation but are built in a
    later phase. Keeps the header and footer genuinely clickable during the
    build instead of linking to 404s.

    Each of these is replaced by a real page — see routes/public.php.
--}}
<x-layouts.app :title="$pageTitle.' — '.__('site.brand.name')">

    <x-section size="lg">
        <div class="flex min-h-[40svh] flex-col justify-center gap-5">
            <x-reveal>
                <x-micro-label>{{ __('site.placeholder_page.label') }}</x-micro-label>
            </x-reveal>

            <x-reveal>
                <h1 class="text-statement-lg font-medium text-ink">{{ $pageTitle }}</h1>
            </x-reveal>

            <x-rule class="max-w-xs" />

            <x-reveal>
                <p class="max-w-measure text-body text-ink-muted">
                    {{ __('site.placeholder_page.body', ['phase' => $phase]) }}
                </p>
            </x-reveal>

            <x-reveal>
                <x-arrow-link :href="route('home')">{{ __('site.nav.home') }}</x-arrow-link>
            </x-reveal>
        </div>
    </x-section>

</x-layouts.app>

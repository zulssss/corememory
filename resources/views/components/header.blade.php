{{--
    Sticky minimal header: wordmark left, nav, one outlined CTA.
    Alpine holds only the mobile menu open/closed state — nothing more.
--}}
@php
    $nav = [
        ['route' => 'work', 'label' => __('site.nav.work')],
        ['route' => 'packages', 'label' => __('site.nav.packages')],
        ['route' => 'about', 'label' => __('site.nav.about')],
        ['route' => 'journal', 'label' => __('site.nav.journal')],
        ['route' => 'contact', 'label' => __('site.nav.contact')],
    ];
@endphp

<header
    x-data="{ open: false }"
    @keydown.escape.window="open = false"
    class="sticky top-0 z-50 border-b border-rule bg-paper/90 backdrop-blur-sm"
    style="--header-height: 4.25rem"
>
    <div class="page-gutter flex h-[var(--header-height)] items-center justify-between gap-6">

        {{-- Wordmark --}}
        {{-- Sized by height, not width: the wordmark's aspect ratio does the
             rest, so it stays optically level with the nav at any header height. --}}
        <a href="{{ route('home') }}"
           class="shrink-0 text-ink transition-colors hover:text-accent"
           aria-label="{{ __('site.brand.name') }} — {{ __('site.nav.home') }}">
            <x-logo class="block h-[1.15rem] w-auto sm:h-[1.35rem]" />
        </a>

        {{-- Desktop navigation --}}
        <nav class="hidden items-center gap-8 md:flex" aria-label="{{ __('site.nav.primary') }}">
            @foreach ($nav as $item)
                <a href="{{ route($item['route']) }}"
                   @class([
                       'micro-label transition-colors hover:text-ink',
                       'text-ink' => request()->routeIs($item['route']),
                   ])
                   @if (request()->routeIs($item['route'])) aria-current="page" @endif>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="flex items-center gap-4">
            {{-- The one CTA. Outlined, sharp corners, arrow prefix per the reference. --}}
            <a href="{{ route('availability') }}"
               class="hidden border border-ink px-4 py-2 font-mono text-micro uppercase tracking-micro text-ink transition-colors hover:bg-ink hover:text-paper sm:inline-block">
                ↗ {{ __('site.cta.check_availability') }}
            </a>

            {{-- Mobile menu toggle --}}
            <button type="button"
                    @click="open = !open"
                    :aria-expanded="open ? 'true' : 'false'"
                    aria-controls="mobile-nav"
                    class="micro-label text-ink md:hidden">
                <span x-show="!open">{{ __('site.nav.menu') }}</span>
                <span x-show="open" x-cloak>{{ __('site.nav.close') }}</span>
            </button>
        </div>
    </div>

    {{-- Mobile navigation --}}
    <nav id="mobile-nav"
         x-show="open"
         x-cloak
         x-transition.opacity
         class="border-t border-rule bg-paper md:hidden"
         aria-label="{{ __('site.nav.primary') }}">
        <ul>
            @foreach ($nav as $item)
                <li class="border-b border-rule">
                    <a href="{{ route($item['route']) }}"
                       class="page-gutter block py-4 font-mono text-caption uppercase tracking-micro text-ink">
                        {{ $item['label'] }}
                    </a>
                </li>
            @endforeach
            <li class="border-b border-rule">
                <a href="{{ route('availability') }}"
                   class="page-gutter block py-4 font-mono text-caption uppercase tracking-micro text-accent">
                    ↗ {{ __('site.cta.check_availability') }}
                </a>
            </li>
        </ul>
    </nav>
</header>

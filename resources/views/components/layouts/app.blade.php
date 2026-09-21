@props([
    'title' => null,
    'description' => null,
    'ogType' => 'website',
    'schema' => [],
    'noindex' => false,
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f7f5f1">

    <title>{{ $title ?? config('app.name') }}</title>
    <meta name="description" content="{{ $description ?? __('site.meta.default_description') }}">

    {{-- Canonical, Open Graph, Twitter card and JSON-LD. Pages pass their own
         schema block through $schema; LocalBusiness is emitted site-wide. --}}
    <x-seo
        :title="$title ?? null"
        :description="$description ?? null"
        :type="$ogType ?? 'website'"
        :schema="$schema ?? []"
        :noindex="$noindex ?? false" />

    {{--
        Set data-motion BEFORE first paint so reveal targets are already hidden
        when the page renders — without this there is a visible flash of the
        final state before GSAP takes over.

        The failsafe matters: because the attribute hides content, a failure to
        load app.js would leave the page blank. So we arm a timer that strips
        the attribute if the bundle hasn't checked in, and the page degrades to
        plain readable HTML instead.
    --}}
    <script>
        (function () {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            var root = document.documentElement;
            root.setAttribute('data-motion', 'on');
            var failsafe = setTimeout(function () {
                root.removeAttribute('data-motion');
            }, 2500);
            window.__coreMemoryMotionReady = function () { clearTimeout(failsafe); };
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen bg-paper text-ink antialiased">

    {{-- Keyboard users land here first and can jump straight past the nav. --}}
    <a href="#main" class="sr-only-focusable absolute left-gutter top-4 z-100 bg-ink px-4 py-2 font-mono text-micro uppercase tracking-micro text-paper">
        {{ __('site.skip_to_content') }}
    </a>

    <x-header />

    <main id="main">
        {{ $slot }}
    </main>

    <x-footer />

    <x-cursor />

    @stack('scripts')
</body>
</html>

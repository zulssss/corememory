{{--
    Per-page metadata: canonical, Open Graph, Twitter card and JSON-LD.

    Every page passes its own title and description through the layout; the
    LocalBusiness block is emitted site-wide, and pages add their own schema
    (Service, Article, ImageGallery) via the $schema prop.
--}}
@props([
    'title' => null,
    'description' => null,
    'image' => null,
    'type' => 'website',
    'schema' => [],       // extra JSON-LD blocks for this page
    'noindex' => false,
])

@php
    use App\Support\Seo;
    use App\Support\Settings;

    $title ??= Settings::get('seo.title') ?? __('site.brand.name');
    $description ??= Settings::get('seo.description') ?? __('site.meta.default_description');
    $image ??= Seo::defaultImage();
    $canonical = url()->current();

    // The site-wide identity always ships; page schema is appended.
    $blocks = array_merge([Seo::localBusiness()], is_array($schema) && isset($schema['@type']) ? [$schema] : $schema);
@endphp

<link rel="canonical" href="{{ $canonical }}">

@if ($noindex)
    <meta name="robots" content="noindex, nofollow">
@endif

{{-- Open Graph --}}
<meta property="og:type" content="{{ $type }}">
<meta property="og:site_name" content="{{ __('site.brand.name') }}">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:locale" content="{{ str_replace('_', '-', app()->getLocale()) }}">
@if ($image)
    <meta property="og:image" content="{{ $image }}">
    <meta property="og:image:alt" content="{{ $title }}">
@endif

{{-- Twitter --}}
<meta name="twitter:card" content="{{ $image ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $title }}">
<meta name="twitter:description" content="{{ $description }}">
@if ($image)
    <meta name="twitter:image" content="{{ $image }}">
@endif

{{-- JSON-LD --}}
@foreach ($blocks as $block)
    <script type="application/ld+json">{!! json_encode($block, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endforeach

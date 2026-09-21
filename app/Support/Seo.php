<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Package;
use App\Models\Post;
use App\Models\Project;
use App\ValueObjects\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Structured data and social metadata.
 *
 * Kept in one class rather than sprinkled through templates so there is a
 * single definition of who the studio is — change the address once and every
 * page's JSON-LD follows.
 *
 * Everything here reads from admin Settings, so the owner controls it without
 * a developer.
 */
final class Seo
{
    /**
     * LocalBusiness — the site-wide identity.
     *
     * Uses the more specific "Photograph" business type rather than plain
     * LocalBusiness: search engines treat the subtype as a stronger signal for
     * "wedding photographer near me", which is the query that matters here.
     *
     * @return array<string, mixed>
     */
    public static function localBusiness(): array
    {
        $phone = Settings::get('contact.phone');
        $address = Settings::get('contact.address');

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Photograph',
            'name' => Settings::get('invoice.registered_name') ?? __('site.brand.name'),
            'alternateName' => __('site.brand.name'),
            'url' => url('/'),
            'description' => Settings::get('seo.description') ?? __('site.meta.default_description'),
            'email' => Settings::get('contact.email'),
            'telephone' => $phone,
            'image' => self::defaultImage(),
            'priceRange' => self::priceRange(),
            'areaServed' => [
                '@type' => 'Country',
                'name' => 'Malaysia',
            ],
            'address' => $address ? [
                '@type' => 'PostalAddress',
                'streetAddress' => str_replace("\n", ', ', trim($address)),
                'addressCountry' => 'MY',
            ] : null,
            'sameAs' => array_values(array_filter([
                Settings::get('social.instagram'),
                Settings::get('social.tiktok'),
            ])),
        ], fn ($value) => $value !== null && $value !== [] && $value !== '');
    }

    /**
     * Service — one per package, so a search result can show what is offered
     * and what it costs.
     *
     * @return array<string, mixed>
     */
    public static function services(Collection $packages): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'itemListElement' => $packages->values()->map(fn (Package $package, int $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'item' => array_filter([
                    '@type' => 'Service',
                    'name' => $package->name,
                    'description' => $package->description,
                    'serviceType' => 'Wedding photography',
                    'provider' => ['@type' => 'Organization', 'name' => __('site.brand.name')],
                    'areaServed' => ['@type' => 'Country', 'name' => 'Malaysia'],
                    'offers' => [
                        '@type' => 'Offer',
                        'price' => number_format($package->price_cents->toRinggit(), 2, '.', ''),
                        'priceCurrency' => 'MYR',
                        'url' => route('packages'),
                        'availability' => 'https://schema.org/InStock',
                    ],
                ]),
            ])->all(),
        ];
    }

    /**
     * Article — for a journal post.
     *
     * @return array<string, mixed>
     */
    public static function article(Post $post): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $post->title,
            'description' => $post->excerpt,
            'datePublished' => $post->published_at?->toIso8601String(),
            'dateModified' => $post->updated_at?->toIso8601String(),
            'image' => $post->getFirstMedia('cover')?->getUrl('full')
                ?? $post->getFirstMedia('cover')?->getUrl(),
            'mainEntityOfPage' => route('journal.show', $post),
            'author' => ['@type' => 'Organization', 'name' => __('site.brand.name')],
            'publisher' => [
                '@type' => 'Organization',
                'name' => __('site.brand.name'),
                'logo' => array_filter(['@type' => 'ImageObject', 'url' => self::defaultImage()]),
            ],
        ], fn ($value) => $value !== null && $value !== '');
    }

    /** ImageGallery — for a wedding story. */
    public static function project(Project $project): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'ImageGallery',
            'name' => $project->title,
            'description' => $project->excerpt,
            'datePublished' => $project->published_at?->toIso8601String(),
            'image' => $project->heroUrl(),
            'locationCreated' => $project->venue ? [
                '@type' => 'Place',
                'name' => $project->venue,
            ] : null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Breadcrumbs. Cheap to add and genuinely improves how a result renders.
     *
     * @param  array<string, string>  $trail  label => url
     */
    public static function breadcrumbs(array $trail): array
    {
        $position = 0;

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($trail)->map(fn (string $url, string $label) => [
                '@type' => 'ListItem',
                'position' => ++$position,
                'name' => $label,
                'item' => $url,
            ])->values()->all(),
        ];
    }

    /** The OG image shown when a link is shared. */
    public static function defaultImage(): ?string
    {
        // Falls back to a featured project's hero — better than nothing when
        // the owner has not uploaded a dedicated share image.
        $project = Project::published()->featured()->ordered()->with('media')->first()
            ?? Project::published()->ordered()->with('media')->first();

        return $project?->heroUrl('full');
    }

    /** "RM 2,500 – RM 9,800", for the LocalBusiness priceRange field. */
    private static function priceRange(): ?string
    {
        // Query builder, not Eloquent pluck(): pluck() applies the Money cast
        // and returns value objects, which min()/max() cannot compare. Asking
        // the database keeps these plain integers.
        $range = DB::table('packages')
            ->where('is_active', true)
            ->selectRaw('MIN(price_cents) AS min_price, MAX(price_cents) AS max_price')
            ->first();

        if ($range === null || $range->min_price === null) {
            return null;
        }

        return (new Money((int) $range->min_price))->formatCompact()
            .' – '.(new Money((int) $range->max_price))->formatCompact();
    }
}

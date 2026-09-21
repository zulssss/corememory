<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProjectCategory;
use App\Support\Lqip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * A wedding story shown on /work.
 *
 * Images live in the media library, not in columns — that is what gives
 * drag-to-reorder galleries and automatic WebP conversions.
 */
class Project extends Model implements HasMedia
{
    use HasFactory, HasSlug, InteractsWithMedia;

    protected $fillable = [
        'title', 'slug', 'category', 'couple_names', 'event_date',
        'venue', 'city', 'state', 'excerpt', 'description', 'crew',
        'is_featured', 'sort_order', 'published_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'category' => ProjectCategory::class,
            'event_date' => 'date',
            'crew' => 'array',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Slugs
    |--------------------------------------------------------------------------
    */

    public function getSlugOptions(): SlugOptions
    {
        // Slugs don't regenerate on update — an existing /work/{slug} URL that
        // has been shared or indexed must keep working after an edit.
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /*
    |--------------------------------------------------------------------------
    | Media
    |--------------------------------------------------------------------------
    */

    public function registerMediaCollections(): void
    {
        // One hero image per project — singleFile() means uploading a new one
        // replaces the old rather than accumulating.
        $this->addMediaCollection('hero')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        // The long-form story gallery. Order is the media `order_column`,
        // which Filament's drag-to-reorder writes to.
        $this->addMediaCollection('gallery')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /**
     * Three conversions, all WebP. The original upload is always preserved —
     * conversions are derived files, so a better size can be generated later
     * without asking the studio to re-upload anything.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 400, 400)
            ->format('webp')
            ->quality(78)
            ->nonQueued();   // admin list thumbnails must exist immediately

        // A 24px blurred placeholder, inlined as a data URI while the real
        // image loads. nonQueued so it exists the moment the upload finishes —
        // a placeholder that arrives after the photograph is pointless.
        $this->addMediaConversion(Lqip::CONVERSION)
            ->fit(Fit::Max, 24, 24)
            ->blur(6)
            ->format('webp')
            ->quality(40)
            ->nonQueued();

        $this->addMediaConversion('grid')
            ->fit(Fit::Max, 900, 1200)
            ->format('webp')
            ->quality(80);

        $this->addMediaConversion('full')
            ->fit(Fit::Max, 1800, 2400)
            ->format('webp')
            ->quality(82);
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function testimonials(): HasMany
    {
        return $this->hasMany(Testimonial::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /** Only projects visible on the public site. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderByDesc('event_date');
    }

    public function scopeCategory(Builder $query, ?ProjectCategory $category): Builder
    {
        return $category === null ? $query : $query->where('category', $category);
    }

    /*
    |--------------------------------------------------------------------------
    | Presentation
    |--------------------------------------------------------------------------
    */

    /** The COUPLE / DATE / VENUE / CREW row under a featured wedding. */
    public function metaRow(): array
    {
        return array_filter([
            __('site.meta_labels.couple') => $this->couple_names,
            __('site.meta_labels.date') => $this->event_date?->translatedFormat('d M Y'),
            __('site.meta_labels.venue') => $this->venue,
            __('site.meta_labels.crew') => $this->crewNames(),
        ]);
    }

    public function crewNames(): ?string
    {
        if (blank($this->crew)) {
            return null;
        }

        return collect($this->crew)->pluck('name')->filter()->implode(', ') ?: null;
    }

    /** Hero image URL at a given conversion, or null so the view can fall back. */
    public function heroUrl(string $conversion = 'full'): ?string
    {
        $media = $this->getFirstMedia('hero');

        return $media?->hasGeneratedConversion($conversion)
            ? $media->getUrl($conversion)
            : $media?->getUrl();
    }

    /** The next/previous story, for the footer navigation on /work/{slug}. */
    public function nextProject(): ?self
    {
        return self::published()
            ->where('sort_order', '>=', $this->sort_order)
            ->whereKeyNot($this->getKey())
            ->ordered()
            ->first()
            ?? self::published()->ordered()->whereKeyNot($this->getKey())->first();
    }

    public function previousProject(): ?self
    {
        return self::published()
            ->where('sort_order', '<=', $this->sort_order)
            ->whereKeyNot($this->getKey())
            ->orderByDesc('sort_order')
            ->first()
            ?? self::published()->orderByDesc('sort_order')->whereKeyNot($this->getKey())->first();
    }
}

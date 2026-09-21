<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Lqip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/** A journal post. Listing and detail pages land in Phase 5. */
class Post extends Model implements HasMedia
{
    use HasFactory, HasSlug, InteractsWithMedia;

    protected $fillable = ['title', 'slug', 'excerpt', 'body', 'published_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 400, 400)->format('webp')->quality(78)->nonQueued();

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
            ->fit(Fit::Max, 900, 1200)->format('webp')->quality(80);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Money as MoneyCast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Package extends Model
{
    use HasFactory, HasSlug;

    protected $fillable = [
        'name', 'slug', 'price_cents', 'price_is_from', 'duration_hours',
        'inclusions', 'description', 'is_popular', 'is_active', 'sort_order',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'price_cents' => MoneyCast::class,
            'price_is_from' => 'boolean',
            'inclusions' => 'array',
            'is_popular' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('price_cents');
    }

    /** Add-ons offered with this package. */
    public function availableAddOns()
    {
        return AddOn::query()
            ->active()
            ->ordered()
            ->get()
            ->filter(fn (AddOn $addOn) => $addOn->appliesTo($this));
    }

    /** "From RM 3,800" or "RM 3,800". */
    public function displayPrice(): string
    {
        $price = $this->price_cents->formatCompact();

        return $this->price_is_from
            ? __('site.packages.from').' '.$price
            : $price;
    }
}

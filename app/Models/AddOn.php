<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Money as MoneyCast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class AddOn extends Model
{
    use HasFactory, HasSlug;

    protected $table = 'add_ons';

    protected $fillable = [
        'name', 'slug', 'price_cents', 'description', 'is_quantifiable',
        'max_qty', 'applies_to', 'is_active', 'sort_order',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'price_cents' => MoneyCast::class,
            'is_quantifiable' => 'boolean',
            'applies_to' => 'array',
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** An empty applies_to means "available with every package". */
    public function appliesTo(Package $package): bool
    {
        return blank($this->applies_to)
            || in_array($package->getKey(), $this->applies_to, false);
    }

    /** Clamp a requested quantity to what this add-on actually allows. */
    public function clampQuantity(int $qty): int
    {
        if (! $this->is_quantifiable) {
            return 1;
        }

        return max(1, min($qty, (int) $this->max_qty));
    }
}

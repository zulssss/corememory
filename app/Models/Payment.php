<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Money as MoneyCast;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Money actually received.
 *
 * The dashboard reports invoiced and collected separately — an invoice raised
 * is not cash in the bank, and treating them as the same number is how a
 * studio convinces itself it had a good month.
 */
class Payment extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'invoice_id', 'amount_cents', 'paid_at', 'method', 'reference',
        'notes', 'gateway', 'gateway_reference', 'raw_response',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount_cents' => MoneyCast::class,
            'paid_at' => 'date',
            'method' => PaymentMethod::class,
            'raw_response' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** An optional photo of the transfer slip. */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('receipt')->singleFile();
    }

    public function scopePaidBetween(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('paid_at', [$from, $to]);
    }
}

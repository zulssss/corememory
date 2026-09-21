<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Money as MoneyCast;
use App\Enums\CostCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A direct cost of delivering one booking. Profit is meaningless without these. */
class BookingCost extends Model
{
    use HasFactory;

    protected $fillable = ['booking_id', 'label', 'category', 'amount_cents', 'is_paid'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'category' => CostCategory::class,
            'amount_cents' => MoneyCast::class,
            'is_paid' => 'boolean',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}

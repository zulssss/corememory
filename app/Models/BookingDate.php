<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SessionSlot;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One date + slot a booking covers. A two-day wedding has two of these.
 *
 * session_slot here may be 'full_day' — that is the couple's choice. It is
 * expanded into concrete slots only when holds are written.
 */
class BookingDate extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id', 'event_date', 'session_slot', 'label', 'venue', 'city', 'state',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'session_slot' => SessionSlot::class,
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function holds(): MorphMany
    {
        return $this->morphMany(SlotHold::class, 'holdable');
    }

    /** "14 Feb 2026 — Morning (9:00 AM – 1:00 PM)" */
    public function describe(): string
    {
        return $this->event_date->translatedFormat('d M Y').' — '.$this->session_slot->labelWithTime();
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SessionSlot;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A date the studio has taken off the calendar by hand.
 *
 * Holds are kept in sync by BlockedDateObserver, so saving or deleting one in
 * the admin panel immediately changes what the public calendar offers.
 */
class BlockedDate extends Model
{
    use HasFactory;

    protected $fillable = ['date', 'session_slot', 'reason'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'session_slot' => SessionSlot::class,
        ];
    }

    public function holds(): MorphMany
    {
        return $this->morphMany(SlotHold::class, 'holdable');
    }

    /** Null session_slot means the whole day. */
    public function slot(): SessionSlot
    {
        return $this->session_slot ?? SessionSlot::FullDay;
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The lifecycle of a booking, from enquiry to completion.
 *
 * A submitted booking is an ENQUIRY, not a confirmation. Only the studio moves
 * it to Confirmed, and only then does it actually hold the date.
 */
enum BookingStatus: string
{
    case Pending = 'pending';
    case Contacted = 'contacted';
    case Quoted = 'quoted';
    case Confirmed = 'confirmed';
    case DepositPaid = 'deposit_paid';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('booking.statuses.'.$this->value);
    }

    /**
     * Does this status take the slot off the calendar?
     *
     * Read from config rather than hardcoded, because the brief requires
     * blocking statuses to be configurable. Adding 'pending' to
     * config/booking.php moves the hard database guarantee to submit time
     * with no code change anywhere.
     */
    public function blocksSlot(): bool
    {
        return in_array($this->value, config('booking.blocking_statuses', []), true);
    }

    /**
     * Shown as "tentative" — someone else has enquired, but it isn't held.
     * A couple can still submit for this slot.
     */
    public function isTentative(): bool
    {
        return in_array($this->value, config('booking.tentative_statuses', []), true);
    }

    /** Statuses that block, as enum cases. Used by the availability queries. */
    public static function blocking(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $status) => $status->blocksSlot()
        ));
    }

    /** Statuses that show as tentative. */
    public static function tentative(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $status) => $status->isTentative()
        ));
    }

    /** Terminal states — no further transition is expected. */
    public function isClosed(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /** Colour key for Filament badges. Maps to design tokens, never raw hex. */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Contacted, self::Quoted => 'info',
            self::Confirmed, self::DepositPaid => 'success',
            self::Completed => 'gray',
            self::Cancelled => 'danger',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status) => [$status->value => $status->label()])
            ->all();
    }
}

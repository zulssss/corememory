<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\SessionSlot;

/**
 * The state of one date + slot, as the calendar should present it.
 *
 * `bookable` is the only thing the wizard should branch on. `reason` exists so
 * an unavailable option can say WHY rather than silently disappearing — the
 * brief is explicit that unavailable combinations are visibly disabled with a
 * reason.
 */
final readonly class SlotAvailability
{
    public const AVAILABLE = 'available';

    public const BLOCKED = 'blocked';

    public const TENTATIVE = 'tentative';

    public const OUT_OF_WINDOW = 'out_of_window';

    public function __construct(
        public SessionSlot $slot,
        public string $state,
        public bool $bookable,
        public ?string $reason = null,
    ) {}

    public static function available(SessionSlot $slot): self
    {
        return new self($slot, self::AVAILABLE, true);
    }

    /** Held by a confirmed booking or an admin block. Not bookable. */
    public static function blocked(SessionSlot $slot, string $reason): self
    {
        return new self($slot, self::BLOCKED, false, $reason);
    }

    /**
     * Another couple has enquired, but nobody holds it. STILL BOOKABLE —
     * that is what "tentative" means. The notice is shown so the couple knows
     * they may be in a race.
     */
    public static function tentative(SessionSlot $slot): self
    {
        return new self($slot, self::TENTATIVE, true, __('booking.availability.tentative_notice'));
    }

    public static function outOfWindow(SessionSlot $slot, string $reason): self
    {
        return new self($slot, self::OUT_OF_WINDOW, false, $reason);
    }

    /**
     * Presentation label — "Morning (9:00 AM – 12:00 PM)".
     *
     * It lives here rather than being reached through to the enum from Blade
     * so the object and toArray() cannot drift into describing different
     * things, which is exactly how the template came to read a `label`
     * property that only existed in the array shape.
     */
    public function label(): string
    {
        return $this->slot->labelWithTime();
    }

    public function toArray(): array
    {
        return [
            'slot' => $this->slot->value,
            'label' => $this->label(),
            'state' => $this->state,
            'bookable' => $this->bookable,
            'reason' => $this->reason,
        ];
    }
}

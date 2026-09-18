<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The session slots a couple can book.
 *
 * THE KEY IDEA — SLOT EXPANSION:
 * `FullDay` is a choice a client makes, but it is never stored as a row in
 * `slot_holds`. It expands into the three concrete slots instead. That single
 * decision is what lets one plain `UNIQUE(event_date, session_slot)` index
 * enforce every conflict rule with no application logic to get wrong:
 *
 *   - Full Day onto a date with an existing Morning  → collides on morning
 *   - Morning onto an existing Full Day              → collides on morning
 *   - The same slot twice                            → collides
 *   - Morning + Evening on one date                  → both fit, correctly
 *
 * See CLAUDE.md § Availability for the full reasoning.
 */
enum SessionSlot: string
{
    case Morning = 'morning';
    case Afternoon = 'afternoon';
    case Evening = 'evening';
    case FullDay = 'full_day';

    /**
     * The slots that physically exist on a date. `FullDay` is deliberately
     * absent — it is a composite, not a real slot.
     *
     * @return array<int, self>
     */
    public static function concrete(): array
    {
        return [self::Morning, self::Afternoon, self::Evening];
    }

    /**
     * Expand a chosen slot into the concrete slots it consumes.
     * This is the only place expansion happens.
     *
     * @return array<int, self>
     */
    public function expand(): array
    {
        return $this === self::FullDay ? self::concrete() : [$this];
    }

    public function isFullDay(): bool
    {
        return $this === self::FullDay;
    }

    /** Human label, translated. Times come from config so the studio can shift them. */
    public function label(): string
    {
        return __('booking.slots.'.$this->value);
    }

    /** "9:00 AM – 12:00 PM". Empty for Full Day, which spans all of them. */
    public function timeRange(): string
    {
        return (string) config("booking.slot_times.{$this->value}", '');
    }

    /** Label plus its clock range, for the calendar step. */
    public function labelWithTime(): string
    {
        $range = $this->timeRange();

        return $range === '' ? $this->label() : $this->label().' ('.$range.')';
    }

    /**
     * Options for a select/radio group.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $slot) => [$slot->value => $slot->labelWithTime()])
            ->all();
    }
}

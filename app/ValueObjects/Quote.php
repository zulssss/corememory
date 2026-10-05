<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Models\Package;

/**
 * A priced quote: the package, the chosen add-ons and what it all adds up to.
 *
 * Built by App\Actions\Bookings\CalculateQuote. The wizard uses it for the
 * live running total, and CreateBooking writes its figures onto the booking —
 * so the number the couple saw on the review step is exactly the number that
 * gets stored. There is no second, separate calculation to drift out of sync.
 */
final readonly class Quote
{
    /**
     * @param  list<QuoteLine>  $lines
     */
    public function __construct(
        public ?Package $package,
        public array $lines,
        public Money $subtotal,
        public Money $addOnsTotal,
        public Money $total,
        public Money $deposit,
        public Money $depositPerEvent,
        public int $eventCount,
    ) {}

    public function balance(): Money
    {
        return $this->total->minus($this->deposit);
    }

    public function hasAddOns(): bool
    {
        return $this->lines !== [];
    }

    public static function empty(Money $depositPerEvent, int $eventCount = 1): self
    {
        return new self(
            package: null,
            lines: [],
            subtotal: Money::zero(),
            addOnsTotal: Money::zero(),
            total: Money::zero(),
            deposit: Money::zero(),
            depositPerEvent: $depositPerEvent,
            eventCount: $eventCount,
        );
    }
}

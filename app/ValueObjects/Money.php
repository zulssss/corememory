<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * An amount of money, always held as an integer number of cents.
 *
 * Money is NEVER stored or calculated as a float anywhere in this codebase.
 * 0.1 + 0.2 !== 0.3 in binary floating point, and on an invoice that becomes a
 * one-sen discrepancy the studio has to explain to a client. Integers can't
 * drift, so every *_cents column is an integer and formatting happens only at
 * the moment of display.
 *
 * Adopted from the ceritaconvo-booking-system repo so the two projects share
 * the same money handling.
 */
final readonly class Money implements JsonSerializable, Stringable
{
    public function __construct(public int $cents) {}

    /** Build from a major-unit amount, e.g. fromRinggit(3800.50). */
    public static function fromRinggit(float|int|string $amount): self
    {
        if (! is_numeric($amount)) {
            throw new InvalidArgumentException('Amount must be numeric.');
        }

        // round() before casting: (int) truncates, so (int) (38.00 * 100) can
        // land on 3799 for values that aren't exactly representable in binary.
        return new self((int) round((float) $amount * 100));
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function plus(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function minus(self $other): self
    {
        return new self($this->cents - $other->cents);
    }

    /** Multiply by a whole quantity, e.g. "extra hour" × 3. */
    public function times(int $quantity): self
    {
        return new self($this->cents * $quantity);
    }

    /**
     * Take a percentage, e.g. a 30% deposit.
     * Rounds half-up to the nearest sen so the deposit and the balance always
     * add back up to exactly the total.
     */
    public function percent(float $percent): self
    {
        return new self((int) round($this->cents * $percent / 100));
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }

    /** The major-unit value, for PDF/CSV output that needs a raw number. */
    public function toRinggit(): float
    {
        return $this->cents / 100;
    }

    /** "RM 3,800.00" — the display form used across the site. */
    public function format(bool $withDecimals = true): string
    {
        return 'RM '.number_format($this->cents / 100, $withDecimals ? 2 : 0);
    }

    /**
     * "RM 3,800" — drops the decimals when the amount is a whole ringgit.
     * Package prices read better without a trailing ".00" on a marketing page.
     */
    public function formatCompact(): string
    {
        return $this->format($this->cents % 100 !== 0);
    }

    public function jsonSerialize(): int
    {
        return $this->cents;
    }

    public function __toString(): string
    {
        return $this->format();
    }
}

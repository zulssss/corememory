<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Models\AddOn;

/** One add-on on a quote, at the quantity chosen and the price captured. */
final readonly class QuoteLine
{
    public function __construct(
        public AddOn $addOn,
        public int $qty,
        public Money $unitPrice,
        public Money $lineTotal,
    ) {}

    /** "Extra hour × 3" */
    public function describe(): string
    {
        return $this->qty > 1
            ? $this->addOn->name.' × '.$this->qty
            : $this->addOn->name;
    }
}

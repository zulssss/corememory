<?php

declare(strict_types=1);

namespace App\Actions\Bookings;

use App\Models\AddOn;
use App\Models\Package;
use App\Support\Settings;
use App\ValueObjects\Money;
use App\ValueObjects\Quote;
use App\ValueObjects\QuoteLine;

/**
 * Turns a package plus a set of chosen add-ons into a priced quote.
 *
 * This is the ONLY place pricing is calculated. The wizard's live total, the
 * review step and the figures written onto the booking all come through here,
 * so the number a couple sees can never differ from the number that is stored.
 */
class CalculateQuote
{
    /**
     * @param  array<int, int>  $addOnQuantities  add_on_id => qty
     */
    public function handle(?Package $package, array $addOnQuantities = []): Quote
    {
        $depositPercent = Settings::depositPercent();

        if (! $package instanceof Package) {
            return Quote::empty($depositPercent);
        }

        $subtotal = $package->price_cents;
        $lines = [];
        $addOnsTotal = Money::zero();

        $addOns = AddOn::query()
            ->active()
            ->whereIn('id', array_keys(array_filter($addOnQuantities, fn (int $qty) => $qty > 0)))
            ->get();

        foreach ($addOns as $addOn) {
            // Ignore an add-on that isn't offered with this package — a stale
            // tab or a hand-edited request must not sneak one onto the quote.
            if (! $addOn->appliesTo($package)) {
                continue;
            }

            $qty = $addOn->clampQuantity((int) $addOnQuantities[$addOn->getKey()]);
            $lineTotal = $addOn->price_cents->times($qty);

            $lines[] = new QuoteLine(
                addOn: $addOn,
                qty: $qty,
                unitPrice: $addOn->price_cents,
                lineTotal: $lineTotal,
            );

            $addOnsTotal = $addOnsTotal->plus($lineTotal);
        }

        $total = $subtotal->plus($addOnsTotal);

        return new Quote(
            package: $package,
            lines: $lines,
            subtotal: $subtotal,
            addOnsTotal: $addOnsTotal,
            total: $total,
            // percent() rounds half-up, so deposit + balance always equals
            // the total exactly — no stray sen to explain to a client.
            deposit: $total->percent($depositPercent),
            depositPercent: $depositPercent,
        );
    }
}

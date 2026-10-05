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
     * @param  int  $eventCount  how many events the booking covers. The deposit
     *                           is charged per event, so a solemnisation plus a
     *                           reception is two deposits.
     */
    public function handle(?Package $package, array $addOnQuantities = [], int $eventCount = 1): Quote
    {
        $depositPerEvent = Settings::depositPerEvent();
        $eventCount = max(1, $eventCount);

        if (! $package instanceof Package) {
            return Quote::empty($depositPerEvent, $eventCount);
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

        /*
         * A flat amount per event, never a percentage — the studio's published
         * terms price it that way, so the deposit does not scale with the
         * package. Capped at the total: a deposit larger than the job itself
         * would make the balance negative and the two invoices stop
         * reconciling to the contract value.
         */
        $deposit = $depositPerEvent->times($eventCount);

        if ($deposit->cents > $total->cents) {
            $deposit = $total;
        }

        return new Quote(
            package: $package,
            lines: $lines,
            subtotal: $subtotal,
            addOnsTotal: $addOnsTotal,
            total: $total,
            deposit: $deposit,
            depositPerEvent: $depositPerEvent,
            eventCount: $eventCount,
        );
    }
}

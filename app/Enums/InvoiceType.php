<?php

declare(strict_types=1);

namespace App\Enums;

enum InvoiceType: string
{
    /** The deposit that confirms a date. */
    case Deposit = 'deposit';

    /** The balance, raised after the event. */
    case Final = 'final';

    /** Anything else — a walk-in, a print order, a re-shoot. */
    case Custom = 'custom';

    public function label(): string
    {
        return __('invoice.types.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $t) => [$t->value => $t->label()])
            ->all();
    }
}

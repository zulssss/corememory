<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use App\ValueObjects\Money;
use Filament\Forms\Components\TextInput;

/**
 * A money field: entered in ringgit, stored as integer cents.
 *
 * Use this for EVERY money field in the admin rather than a hand-rolled
 * TextInput. Two reasons:
 *
 * 1. It is the only place the ringgit <-> cents conversion lives, so a field
 *    added later cannot get the factor wrong.
 *
 * 2. It deliberately does NOT call ->numeric(). Filament's numeric() installs
 *    a state cast that runs floatval() on the value BEFORE formatStateUsing
 *    gets a look at it — and floatval() on a Money object is a fatal
 *    "could not be converted to float". Validation is applied with
 *    ->rule('numeric') instead, which runs after the state is resolved.
 */
class MoneyInput
{
    public static function make(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->prefix('RM')
            ->rule('numeric')
            ->minValue(0)
            ->step(0.01)
            ->helperText('Enter in ringgit, e.g. 6800 or 6800.50.')
            ->formatStateUsing(fn ($state) => match (true) {
                $state === null => null,
                $state instanceof Money => $state->toRinggit(),
                default => (int) $state / 100,
            })
            ->dehydrateStateUsing(fn ($state) => $state === null || $state === ''
                ? null
                : (int) round((float) $state * 100));
    }
}

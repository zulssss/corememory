<?php

declare(strict_types=1);

namespace App\Casts;

use App\ValueObjects\Money as MoneyValue;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Casts an integer "_cents" column to a Money value object and back.
 *
 * Attach it in a model's casts() so the rest of the app never touches raw
 * cents by accident:
 *
 *     protected function casts(): array
 *     {
 *         return ['price_cents' => MoneyCast::class];
 *     }
 *
 * Then `$package->price_cents->format()` gives "RM 3,800.00", while the column
 * itself stays a plain integer.
 *
 * @implements CastsAttributes<MoneyValue|null, MoneyValue|int|float|string|null>
 */
final class Money implements CastsAttributes, SerializesCastableAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?MoneyValue
    {
        return $value === null ? null : new MoneyValue((int) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }

        // Accepts a Money object or a raw integer of cents. A float is treated
        // as cents too, not ringgit — use Money::fromRinggit() to convert.
        return $value instanceof MoneyValue ? $value->cents : (int) $value;
    }

    /**
     * How this value appears in toArray() / toJson().
     *
     * Without this, attributesToArray() hands back the Money OBJECT, which
     * then ends up in Livewire's component state — and Livewire cannot
     * serialise an arbitrary value object, so any Filament edit form
     * containing a money column dies with
     * "Property type not supported in Livewire".
     *
     * It bites even on columns with no form field (subtotal_cents), because
     * Filament fills the form from every attribute, so there is no
     * formatStateUsing to intercept it. Fixing it here fixes every model at
     * once, and keeps API/JSON output as plain integer cents.
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        return $value instanceof MoneyValue ? $value->cents : $value;
    }
}

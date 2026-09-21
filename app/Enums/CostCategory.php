<?php

declare(strict_types=1);

namespace App\Enums;

/** Groups direct costs so the dashboard can show where the money goes. */
enum CostCategory: string
{
    case Photographer = 'photographer';
    case Videographer = 'videographer';
    case Editor = 'editor';
    case Travel = 'travel';
    case Accommodation = 'accommodation';
    case Equipment = 'equipment';
    case Printing = 'printing';
    case Other = 'other';

    public function label(): string
    {
        return __('invoice.cost_categories.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }
}

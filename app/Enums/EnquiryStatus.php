<?php

declare(strict_types=1);

namespace App\Enums;

enum EnquiryStatus: string
{
    case New = 'new';
    case Replied = 'replied';
    case Closed = 'closed';

    public function label(): string
    {
        return __('contact.statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::Replied => 'success',
            self::Closed => 'gray',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $s) => [$s->value => $s->label()])
            ->all();
    }
}

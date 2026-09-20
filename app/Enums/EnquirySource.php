<?php

declare(strict_types=1);

namespace App\Enums;

/** How a couple found the studio. Drives the source breakdown on the dashboard. */
enum EnquirySource: string
{
    case Instagram = 'instagram';
    case TikTok = 'tiktok';
    case Google = 'google';
    case Referral = 'referral';
    case WalkIn = 'walk_in';
    case Other = 'other';

    public function label(): string
    {
        return __('booking.sources.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $s) => [$s->value => $s->label()])
            ->all();
    }
}

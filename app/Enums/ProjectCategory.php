<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The kinds of work the studio shows in the portfolio.
 *
 * These double as the filter options on /work, so the order of cases is the
 * order the filters render in.
 */
enum ProjectCategory: string
{
    case Wedding = 'wedding';
    case PreWedding = 'pre_wedding';
    case Nikah = 'nikah';
    case Engagement = 'engagement';
    case Video = 'video';

    public function label(): string
    {
        return __('site.categories.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $category) => [$category->value => $category->label()])
            ->all();
    }
}

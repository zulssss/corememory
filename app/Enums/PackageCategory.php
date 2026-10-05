<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How the studio's pricelist is organised.
 *
 * The published pricelist is built on two axes: what is being captured
 * (photo, video, or both) and how many events it covers (one, or a
 * solemnisation plus a reception). The coverage type is the axis a couple
 * picks FIRST, so it is the one that groups the packages page; single vs
 * double lives in the package name and its duration.
 *
 * Sessions are the standalone shoots that are not a wedding day at all.
 */
enum PackageCategory: string
{
    case Photo = 'photo';
    case Video = 'video';
    case PhotoVideo = 'photo_video';
    case Session = 'session';

    /** Display order on /packages — cheapest commitment first. */
    public static function ordered(): array
    {
        return [self::Photo, self::Video, self::PhotoVideo, self::Session];
    }

    public function label(): string
    {
        return __('site.packages.categories.'.$this->value);
    }

    public function description(): string
    {
        return __('site.packages.category_notes.'.$this->value);
    }
}

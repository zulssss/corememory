<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Low-Quality Image Placeholders — the blurred colour behind an image while it
 * loads.
 *
 * Why it matters here: this site is mostly photographs, most visitors arrive on
 * a phone over 4G at night, and a grid of empty grey boxes reads as broken.
 * A blurred version of the real photograph reads as loading.
 *
 * The tiny image is a 24px-wide WebP conversion. Base64-encoding it on every
 * request would be wasteful, so the data URI is cached for a month, keyed by
 * media id and the file's update time — so replacing an image invalidates its
 * placeholder automatically without anything to remember.
 */
final class Lqip
{
    /** Roughly 400–900 bytes at 24px wide. Inlining is cheaper than a request. */
    public const CONVERSION = 'lqip';

    public static function dataUri(?Media $media): ?string
    {
        if (! $media instanceof Media || ! $media->hasGeneratedConversion(self::CONVERSION)) {
            return null;
        }

        $key = sprintf('lqip.%d.%s', $media->getKey(), $media->updated_at?->timestamp ?? '0');

        return Cache::remember($key, now()->addMonth(), function () use ($media): ?string {
            try {
                $path = $media->getPath(self::CONVERSION);

                if (! is_readable($path)) {
                    return null;
                }

                $bytes = file_get_contents($path);

                // A placeholder that isn't dramatically smaller than the real
                // image is pointless — bail rather than inline something huge.
                if ($bytes === false || strlen($bytes) > 4096) {
                    return null;
                }

                return 'data:image/webp;base64,'.base64_encode($bytes);
            } catch (\Throwable $e) {
                // A missing placeholder must never break the page.
                Log::debug('LQIP unavailable', ['media' => $media->getKey(), 'error' => $e->getMessage()]);

                return null;
            }
        });
    }
}

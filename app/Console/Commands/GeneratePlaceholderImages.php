<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Generates neutral placeholder photography into public/images/placeholder/.
 *
 * Why generate rather than ship image files:
 *   - Nothing is hotlinked from any reference site, and no third-party licence
 *     is involved. Every pixel here is produced locally.
 *   - They are real rasters (PNG), not SVGs, so the media library's WebP
 *     conversion pipeline is genuinely exercised by the seeders. An SVG would
 *     silently skip conversions and hide a broken image pipeline until real
 *     photographs were uploaded.
 *
 * Run automatically by ProjectSeeder when the files are missing, so
 * `migrate:fresh --seed` works on a clean checkout.
 *
 *     php artisan corememory:placeholders --force
 */
class GeneratePlaceholderImages extends Command
{
    protected $signature = 'corememory:placeholders {--force : Regenerate files that already exist}';

    protected $description = 'Generate neutral placeholder images for seeding';

    /** Aspect ratios and how many of each to produce, keyed by filename prefix. */
    private const SETS = [
        '16x9' => ['width' => 1920, 'height' => 1080, 'count' => 4],
        '3x2' => ['width' => 1600, 'height' => 1067, 'count' => 10],
        '4x5' => ['width' => 1280, 'height' => 1600, 'count' => 6],
        '1x1' => ['width' => 1200, 'height' => 1200, 'count' => 4],
    ];

    /**
     * Warm neutral tones that sit with the off-white paper background, so a
     * seeded site looks deliberate rather than broken.
     *
     * @var array<int, array{int, int, int}>
     */
    private const TONES = [
        [226, 222, 215], [214, 208, 199], [199, 192, 181], [183, 176, 165],
        [166, 159, 148], [150, 143, 133], [206, 197, 188], [178, 170, 162],
        [232, 228, 221], [193, 186, 177],
    ];

    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('The GD extension is required to generate placeholders.');

            return self::FAILURE;
        }

        $directory = public_path('images/placeholder');

        if (! is_dir($directory) && ! mkdir($directory, 0o755, true) && ! is_dir($directory)) {
            $this->error("Could not create {$directory}.");

            return self::FAILURE;
        }

        $created = 0;
        $skipped = 0;

        foreach (self::SETS as $prefix => $set) {
            for ($i = 1; $i <= $set['count']; $i++) {
                $filename = sprintf('%s-%02d.png', $prefix, $i);
                $path = $directory.DIRECTORY_SEPARATOR.$filename;

                if (file_exists($path) && ! $this->option('force')) {
                    $skipped++;

                    continue;
                }

                $this->render($path, $set['width'], $set['height'], $prefix, $i);
                $created++;
            }
        }

        $this->info("Placeholders: {$created} written, {$skipped} already present → public/images/placeholder");

        return self::SUCCESS;
    }

    private function render(string $path, int $width, int $height, string $prefix, int $index): void
    {
        $image = imagecreatetruecolor($width, $height);

        // Cycle the tones so adjacent images in a grid never share a colour.
        $tone = self::TONES[($index - 1) % count(self::TONES)];
        $background = imagecolorallocate($image, ...$tone);
        imagefilledrectangle($image, 0, 0, $width, $height, $background);

        // A slightly darker band across the lower third. Gives each frame a
        // sense of composition so grid layouts can be judged properly.
        $band = imagecolorallocate(
            $image,
            max(0, $tone[0] - 14),
            max(0, $tone[1] - 14),
            max(0, $tone[2] - 14),
        );
        imagefilledrectangle($image, 0, (int) ($height * 0.66), $width, $height, $band);

        // Label, so nobody mistakes a placeholder for real client photography.
        $label = strtoupper(sprintf('PLACEHOLDER %s %02d', $prefix, $index));
        $ink = imagecolorallocate(
            $image,
            max(0, $tone[0] - 60),
            max(0, $tone[1] - 60),
            max(0, $tone[2] - 60),
        );

        $font = 5;
        $textWidth = imagefontwidth($font) * strlen($label);
        imagestring(
            $image,
            $font,
            (int) (($width - $textWidth) / 2),
            (int) ($height / 2 - imagefontheight($font) / 2),
            $label,
            $ink,
        );

        imagepng($image, $path, 6);
        imagedestroy($image);
    }
}

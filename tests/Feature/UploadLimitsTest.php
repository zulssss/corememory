<?php

declare(strict_types=1);

use App\Filament\Resources\Projects\Schemas\ProjectForm;

/**
 * PHP rejects an oversized upload in the SAPI, before Laravel boots. Filament
 * validation never runs, so the couple — or the studio owner — sees only
 * "Error during upload" with no reason given.
 *
 * That is exactly how a 3 MB wedding photograph failed against PHP's compiled
 * default of upload_max_filesize=2M while the admin form advertised 12 MB.
 * These tests fail loudly when the two drift apart again.
 */

/** Turn "24M" / "512K" / "1G" into bytes. */
function iniBytes(string $value): int
{
    $value = trim($value);
    $unit = strtolower($value[strlen($value) - 1]);
    $number = (int) $value;

    return match ($unit) {
        'g' => $number * 1024 ** 3,
        'm' => $number * 1024 ** 2,
        'k' => $number * 1024,
        default => $number,
    };
}

/** The largest upload any admin form claims to accept, in bytes. */
function advertisedMaxUploadBytes(): int
{
    // ProjectForm is the heaviest: full-resolution wedding photographs.
    return 12 * 1024 * 1024;
}

it('accepts uploads as large as the admin form advertises', function () {
    $php = iniBytes((string) ini_get('upload_max_filesize'));

    expect($php)->toBeGreaterThanOrEqual(
        advertisedMaxUploadBytes(),
        sprintf(
            'PHP upload_max_filesize is %s but the admin form offers %s. '
            .'Raise upload_max_filesize in php.ini, or lower the form\'s maxSize() — '
            .'otherwise an upload in between fails with an unexplained "Error during upload".',
            ini_get('upload_max_filesize'),
            '12M',
        )
    );
});

it('leaves room for form fields alongside the file', function () {
    // post_max_size caps the WHOLE request. If it only matched
    // upload_max_filesize, a max-size file plus its form fields would tip over.
    $post = iniBytes((string) ini_get('post_max_size'));
    $upload = iniBytes((string) ini_get('upload_max_filesize'));

    expect($post)->toBeGreaterThan(
        $upload,
        sprintf('post_max_size (%s) must exceed upload_max_filesize (%s).',
            ini_get('post_max_size'), ini_get('upload_max_filesize'))
    );
});

it('still enforces a ceiling in the form, so PHP is never the first line of defence', function () {
    // A form-level maxSize gives a readable validation message. Without it the
    // only limit is PHP's, whose failure mode is the opaque one above.
    $source = file_get_contents(
        (new ReflectionClass(ProjectForm::class))->getFileName()
    );

    expect($source)->toContain('maxSize(');
});

<?php

declare(strict_types=1);

/*
 * Copies the PUBLIC site's content from the local MySQL database into the
 * SQLite file the Vercel preview runs on. Run by `./scripts/vercel deploy`,
 * after it has migrated that file.
 *
 * The preview has no admin, so nothing private goes in: no users, invoices,
 * payments, enquiries or notes. Bookings ARE copied, because pending ones
 * drive the calendar's "another enquiry is pending" state — but with every
 * personal detail replaced.
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$target = $argv[1] ?? exit("usage: php scripts/vercel-snapshot.php review.sqlite\n");

config(['database.connections.review' => [
    'driver' => 'sqlite',
    'database' => $target,
    'prefix' => '',
    'foreign_key_constraints' => false,
]]);

$tables = [
    'packages', 'add_ons', 'projects', 'testimonials', 'posts', 'media', 'settings',
    'blocked_dates', 'slot_holds', 'sequences', 'bookings', 'booking_dates',
];

$scrub = [
    'bookings' => fn (array $row): array => [
        ...$row,
        'partner_one_name' => 'Couple',
        'partner_two_name' => null,
        'email' => 'hidden@example.invalid',
        'phone' => '000-000 0000',
        'notes' => null,
        'admin_notes' => null,
    ],
];

$source = DB::connection('mysql');
$review = DB::connection('review');

foreach ($tables as $table) {
    $review->table($table)->delete();

    $batch = [];
    $count = 0;
    foreach ($source->table($table)->cursor() as $row) {
        $row = (array) $row;
        $batch[] = isset($scrub[$table]) ? $scrub[$table]($row) : $row;
        $count++;

        if (count($batch) === 100) {
            $review->table($table)->insert($batch);
            $batch = [];
        }
    }
    if ($batch !== []) {
        $review->table($table)->insert($batch);
    }

    printf("  %-14s %d\n", $table, $count);
}

$review->statement('VACUUM');

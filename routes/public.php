<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Public Site Routes
|------------------------------------------------------------------------------
| Everything a couple sees. Split out of web.php (a pattern borrowed from the
| ceritaconvo repo) so the public site and the admin surface stay legible as
| separate concerns as they grow.
|
| PHASE 1: only the homepage shell is real. The rest render a clearly-labelled
| placeholder so the header and footer navigation is genuinely clickable while
| the site is built out. Each phase replaces placeholders with real pages:
|   Phase 2 → /work, /work/{slug}
|   Phase 3 → /packages, /availability, /book
|   Phase 5 → /about, /contact, /journal
*/

Route::get('/', fn () => view('pages.home'))->name('home');

/** @var array<string, string> Placeholder routes, with the phase that fills them in. */
$upcoming = [
    'work' => 'Phase 2',
    'packages' => 'Phase 3',
    'availability' => 'Phase 3',
    'book' => 'Phase 3',
    'about' => 'Phase 5',
    'journal' => 'Phase 5',
    'contact' => 'Phase 5',
];

foreach ($upcoming as $slug => $phase) {
    Route::get("/{$slug}", fn () => view('pages.placeholder', [
        'pageTitle' => ucfirst($slug),
        'phase' => $phase,
    ]))->name($slug);
}

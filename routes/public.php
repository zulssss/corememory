<?php

declare(strict_types=1);

use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\WorkController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Public Site Routes
|------------------------------------------------------------------------------
| Everything a couple sees. Split out of web.php (a pattern borrowed from the
| ceritaconvo repo) so the public site and the admin surface stay legible as
| separate concerns as they grow.
|
| Placeholder routes below render a clearly-labelled holding page so the header
| and footer navigation is genuinely clickable while the site is built out.
| Each is annotated with the phase that replaces it.
*/

Route::get('/', HomeController::class)->name('home');

// Portfolio — Phase 2
Route::get('/work', [WorkController::class, 'index'])->name('work');
Route::get('/work/{project}', [WorkController::class, 'show'])->name('work.show');

/** @var array<string, string> Placeholder routes, with the phase that fills them in. */
$upcoming = [
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

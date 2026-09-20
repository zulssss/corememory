<?php

declare(strict_types=1);

use App\Http\Controllers\Public\AvailabilityController;
use App\Http\Controllers\Public\BookingController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\PackageController;
use App\Http\Controllers\Public\WorkController;
use App\Livewire\BookingWizard;
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

// Pricing, availability and booking — Phase 3
Route::get('/packages', PackageController::class)->name('packages');
Route::get('/availability', AvailabilityController::class)->name('availability');

// The booking wizard is a full-page Livewire component: the calendar has to
// respond as the couple clicks, and the wizard state lives server-side in the
// session across steps.
Route::get('/book', BookingWizard::class)->name('book');
Route::get('/book/thank-you/{reference}', [BookingController::class, 'thanks'])->name('book.thanks');

/** @var array<string, string> Placeholder routes, with the phase that fills them in. */
$upcoming = [
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

<?php

declare(strict_types=1);

use App\Http\Controllers\Public\AboutController;
use App\Http\Controllers\Public\AvailabilityController;
use App\Http\Controllers\Public\BookingController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\JournalController;
use App\Http\Controllers\Public\PackageController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\Public\TermsController;
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

// About, journal and contact — Phase 5
Route::get('/about', AboutController::class)->name('about');

// The studio's booking terms, published rather than sent on request.
Route::get('/terms', TermsController::class)->name('terms');

Route::get('/journal', [JournalController::class, 'index'])->name('journal');
Route::get('/journal/{post}', [JournalController::class, 'show'])->name('journal.show');

Route::get('/contact', [ContactController::class, 'show'])->name('contact');

// Rate limited: a contact form is the easiest thing on a site to abuse, and
// the honeypot alone only stops the lazy bots.
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:6,60')
    ->name('contact.store');

// SEO
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

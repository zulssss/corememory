<?php

declare(strict_types=1);

use App\Http\Controllers\InvoiceDownloadController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Admin-adjacent Routes (outside Filament)
|------------------------------------------------------------------------------
| The Filament panel registers its own routes at /admin via
| app/Providers/Filament/AdminPanelProvider.php — nothing here duplicates that.
*/

/*
 * Invoice download.
 *
 * Deliberately NOT behind auth: a couple opens this from an email and has no
 * account. The `signed` middleware is what protects it — the URL carries a
 * signature tied to the invoice id and an expiry, so it cannot be guessed by
 * incrementing an id, and it dies on schedule.
 */
Route::get('/invoices/{invoice}/download', InvoiceDownloadController::class)
    ->middleware('signed')
    ->name('invoices.download');

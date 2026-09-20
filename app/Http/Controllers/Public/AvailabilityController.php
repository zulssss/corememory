<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * /availability — a standalone quick check that feeds into the booking wizard.
 *
 * The rendering is a Livewire component so the calendar responds as the couple
 * clicks, without a page reload. This controller only provides the shell.
 */
class AvailabilityController extends Controller
{
    public function __invoke(): View
    {
        return view('pages.availability');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * /terms — the studio's booking terms, published rather than sent on request.
 *
 * Same premise as publishing prices: a couple should be able to read what they
 * are agreeing to before they hand over a deposit, not after. The booking
 * wizard links here from its consent checkbox.
 */
class TermsController extends Controller
{
    public function __invoke(): View
    {
        return view('pages.terms');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Contracts\View\View;

class BookingController extends Controller
{
    /**
     * The thank-you page.
     *
     * Looked up by reference rather than id, and deliberately shows nothing
     * sensitive — a reference is guessable enough that it must not expose a
     * couple's phone number or email to whoever tries CM-2026-0002.
     */
    public function thanks(string $reference): View
    {
        $booking = Booking::query()
            ->where('reference', $reference)
            ->with(['dates', 'package'])
            ->firstOrFail();

        return view('pages.book.thanks', ['booking' => $booking]);
    }
}

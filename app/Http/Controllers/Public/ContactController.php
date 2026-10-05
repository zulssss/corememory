<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEnquiryRequest;
use App\Mail\EnquiryReceivedMail;
use App\Models\Enquiry;
use App\Support\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function show(): View
    {
        return view('pages.contact');
    }

    public function store(StoreEnquiryRequest $request): RedirectResponse
    {
        // A filled honeypot is a bot. Pretend it worked rather than telling it
        // why it failed.
        if (filled($request->input(config('booking.honeypot_field')))) {
            return redirect()->route('contact')->with('enquiry_sent', true);
        }

        $enquiry = Enquiry::create($request->safe()->only(['name', 'email', 'phone', 'message']));

        // Queued. A message already saved must never be lost to a mail outage,
        // so a failure is logged rather than thrown at the visitor.
        try {
            /*
             * The same chain SendBookingNotifications uses, in the same order.
             * This previously fell back to mail.from.address — the address the
             * site sends FROM — so enquiry notifications landed in the wrong
             * mailbox while booking notifications went to the studio.
             */
            $studio = Settings::get('contact.email')
                ?? config('mail.studio_address')
                ?? config('mail.from.address');

            if (filled($studio)) {
                Mail::to($studio)->queue(new EnquiryReceivedMail($enquiry));
            } else {
                Log::warning('No studio address configured — enquiry notification not sent', [
                    'enquiry' => $enquiry->id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to queue enquiry notification', [
                'enquiry' => $enquiry->id,
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()->route('contact')->with('enquiry_sent', true);
    }
}

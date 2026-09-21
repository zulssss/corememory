<x-mail::message>
# {{ __('invoice.mail.heading', ['number' => $invoice->number]) }}

{{ __('invoice.mail.intro', ['name' => $invoice->client_name]) }}

<x-mail::panel>
**{{ __('invoice.mail.amount_due') }}:** {{ $invoice->outstanding()->format() }}
@if ($invoice->due_at)

{{ __('invoice.mail.due_by', ['date' => $invoice->due_at->translatedFormat('d M Y')]) }}
@endif
</x-mail::panel>

<x-mail::button :url="$downloadUrl">
{{ __('invoice.mail.download') }}
</x-mail::button>

{{ __('invoice.mail.link_expiry', ['days' => $linkDays]) }}

@if ($invoice->booking)
**{{ __('invoice.booking_reference') }}:** {{ $invoice->booking->reference }}
@endif

{{ __('invoice.thank_you') }}<br>
{{ __('site.brand.name') }}
</x-mail::message>

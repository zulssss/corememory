<x-mail::message>
# {{ __('mail.client.heading', ['name' => $booking->partner_one_name]) }}

{{ __('mail.client.intro') }}

<x-mail::panel>
**{{ __('booking.thanks.reference') }}:** {{ $booking->reference }}
</x-mail::panel>

## {{ __('booking.wizard.your_dates') }}

@foreach ($booking->dates as $date)
- **{{ $date->event_date->translatedFormat('d M Y') }}** — {{ $date->session_slot->labelWithTime() }}@if ($date->label) ({{ $date->label }})@endif
@if ($date->venue){{ '  ' }}
  {{ $date->venue }}@if ($date->city), {{ $date->city }}@endif
@endif
@endforeach

@if ($booking->package)
## {{ __('booking.wizard.your_package') }}

**{{ $booking->package->name }}** — {{ $booking->subtotal_cents->formatCompact() }}

@foreach ($booking->package->inclusions ?? [] as $inclusion)
- {{ $inclusion }}
@endforeach
@endif

@if ($booking->addOns->isNotEmpty())
## {{ __('site.packages.add_ons') }}

@foreach ($booking->addOns as $addOn)
- {{ $addOn->name }}@if ($addOn->pivot->qty > 1) × {{ $addOn->pivot->qty }}@endif — {{ (new App\ValueObjects\Money($addOn->pivot->line_total_cents))->formatCompact() }}
@endforeach
@endif

## {{ __('booking.wizard.running_total') }}

| | |
|:--|--:|
| {{ __('booking.wizard.package_line') }} | {{ $booking->subtotal_cents->formatCompact() }} |
| {{ __('booking.wizard.add_ons_line') }} | {{ $booking->addons_total_cents->formatCompact() }} |
| **{{ __('booking.wizard.running_total') }}** | **{{ $booking->estimated_total_cents->formatCompact() }}** |
| {{ __('booking.wizard.deposit_line', ['percent' => (int) App\Support\Settings::depositPercent()]) }} | {{ $booking->deposit_cents->formatCompact() }} |
| {{ __('booking.wizard.balance_line') }} | {{ $booking->balance()->formatCompact() }} |

{{-- REQUIRED: the availability disclaimer, in the confirmation email. --}}
<x-mail::panel>
{{ __('booking.availability_disclaimer') }}
</x-mail::panel>

## {{ __('booking.thanks.what_next') }}

@foreach (__('booking.thanks.steps') as $i => $step)
{{ $i + 1 }}. {{ $step }}
@endforeach

{{ __('booking.thanks.response_time') }}

{{ __('booking.not_a_confirmation') }}

{{ __('mail.client.sign_off') }}<br>
{{ __('site.brand.name') }}
</x-mail::message>

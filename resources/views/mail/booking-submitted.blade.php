<x-mail::message>
# {{ __('mail.studio.heading') }}

**{{ $booking->coupleNames() }}** — {{ $booking->reference }}

<x-mail::button :url="$adminUrl">
{{ __('mail.studio.open_in_admin') }}
</x-mail::button>

## {{ __('booking.wizard.your_dates') }}

@foreach ($booking->dates as $date)
- **{{ $date->event_date->translatedFormat('d M Y') }}** — {{ $date->session_slot->labelWithTime() }}@if ($date->label) ({{ $date->label }})@endif
@if ($date->venue){{ '  ' }}
  {{ $date->venue }}@if ($date->city), {{ $date->city }}@endif@if ($date->state), {{ $date->state }}@endif
@endif
@endforeach

## {{ __('booking.wizard.your_details') }}

| | |
|:--|:--|
| {{ __('booking.fields.email') }} | {{ $booking->email }} |
| {{ __('booking.fields.phone') }} | {{ $booking->phone }} |
| {{ __('booking.fields.guest_count') }} | {{ $booking->guest_count ?? '—' }} |
| {{ __('booking.fields.source') }} | {{ $booking->source?->label() ?? '—' }} |

@if ($booking->notes)
## {{ __('booking.fields.notes') }}

{{ $booking->notes }}
@endif

## {{ __('booking.wizard.running_total') }}

| | |
|:--|--:|
| {{ $booking->package?->name ?? '—' }} | {{ $booking->subtotal_cents->formatCompact() }} |
@foreach ($booking->addOns as $addOn)
| {{ $addOn->name }}@if ($addOn->pivot->qty > 1) × {{ $addOn->pivot->qty }}@endif | {{ (new App\ValueObjects\Money($addOn->pivot->line_total_cents))->formatCompact() }} |
@endforeach
| **{{ __('booking.wizard.running_total') }}** | **{{ $booking->estimated_total_cents->formatCompact() }}** |
| {{ __('booking.wizard.deposit_line', ['amount' => App\Support\Settings::depositPerEvent()->formatCompact()]) }} | {{ $booking->deposit_cents->formatCompact() }} |

{{ __('mail.studio.footer') }}
</x-mail::message>

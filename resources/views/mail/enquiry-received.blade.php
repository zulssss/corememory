<x-mail::message>
# {{ __('contact.mail.heading') }}

**{{ $enquiry->name }}**
{{ $enquiry->email }}@if ($enquiry->phone) · {{ $enquiry->phone }}@endif

<x-mail::panel>
{{ $enquiry->message }}
</x-mail::panel>

<x-mail::button :url="url('/admin/enquiries')">
{{ __('contact.mail.open_in_admin') }}
</x-mail::button>
</x-mail::message>

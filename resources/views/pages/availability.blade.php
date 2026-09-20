{{--
    /availability — a standalone quick check.

    It is the same wizard component, which means one calendar implementation
    rather than two that can drift apart. A couple who lands here and likes
    what they see is already on step 1 of the booking.
--}}
<x-layouts.app
    :title="__('site.cta.check_availability').' — '.__('site.brand.name')"
    :description="__('booking.availability_disclaimer')"
>
    @livewire('booking-wizard')
</x-layouts.app>

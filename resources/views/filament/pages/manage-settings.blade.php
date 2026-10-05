<x-filament-panels::page>
    <form wire:submit="save" class="grid gap-y-6">
        {{ $this->form }}

        {{-- Filament's own actions component rather than a hand-rolled flex row:
             it carries the panel's standard action spacing, so the button sits
             the same distance below the form as everywhere else in the admin.
             The previous `mt-6` stacked on top of the page's own gap and left a
             visible hole under the last card. --}}
        <x-filament::actions
            :actions="$this->getFormActions()"
            alignment="end"
        />
    </form>
</x-filament-panels::page>

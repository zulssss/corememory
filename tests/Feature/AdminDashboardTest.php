<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\SessionSlot;
use App\Filament\Pages\FinanceDashboard;
use App\Models\Booking;
use App\Models\BookingCost;
use App\Models\BookingDate;
use App\Models\Invoice;
use App\Models\User;
use App\Services\FinanceReport;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

/*
 * The admin home is the sales & profit dashboard, for owner and staff alike.
 */

beforeEach(function () {
    Cache::flush();
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->create();
    $this->owner->assignRole('admin');
    $this->staff = User::factory()->create();
    $this->staff->assignRole('staff');
});

it('opens sales & profit at /admin for owner and staff', function (string $who) {
    $this->actingAs($this->{$who})
        ->get('/admin')
        ->assertOk()
        ->assertSee('Needs attention')
        ->assertSee('Gross profit')
        ->assertSee('Revenue, cost and profit');
})->with(['owner', 'staff']);

it('no longer shows the stock Welcome and Filament cards', function () {
    $this->actingAs($this->owner)
        ->get('/admin')
        ->assertDontSee('filamentphp.com')
        ->assertDontSee('fi-wi-account', false);
});

it('captions direct costs as costs, not as the next 30 days', function () {
    $this->actingAs($this->owner)
        ->get('/admin')
        ->assertSee('Event costs in this period');
});

it('counts what needs attention, whatever period is selected', function () {
    Booking::factory()->count(2)->create(['status' => BookingStatus::Pending]);

    $soon = Booking::factory()->create(['status' => BookingStatus::Confirmed]);
    BookingDate::factory()->for($soon)->on(today()->addDays(10)->toDateString(), SessionSlot::Morning)->create();

    Invoice::factory()->overdue()->totalling(150000)->create();

    $this->actingAs($this->owner);

    $attention = Livewire::test(FinanceDashboard::class)
        ->call('applyPreset', 'last_month')
        ->instance()->attention;

    expect($attention['pending'])->toBe(2)
        ->and($attention['upcoming'])->toBe(1)
        ->and($attention['overdue_count'])->toBe(1);
});

it('links overdue invoices for the owner but not for staff', function () {
    $invoice = Invoice::factory()->overdue()->totalling(150000)->create();

    $this->actingAs($this->owner)->get('/admin')
        ->assertSee('/admin/invoices/'.$invoice->getKey().'/edit', false);

    $this->actingAs($this->staff)->get('/admin')
        ->assertSee($invoice->number)
        ->assertDontSee('/admin/invoices/'.$invoice->getKey().'/edit', false);
});

it('highlights the preset that matches the chosen dates', function () {
    $this->actingAs($this->owner);

    $page = Livewire::test(FinanceDashboard::class);
    expect($page->instance()->activePreset)->toBe('this_month');

    $page->call('applyPreset', 'this_year');
    expect($page->instance()->activePreset)->toBe('this_year')
        ->and($page->get('filters.from'))->toBe(today()->startOfYear()->toDateString());

    $page->set('filters.from', today()->subDays(3)->toDateString());
    expect($page->instance()->activePreset)->toBeNull();
});

it('renders twice in a row from the cache', function () {
    // Object-serialisation bugs in a cache only show on the second read.
    $this->actingAs($this->owner);

    $this->get('/admin')->assertOk();
    $this->get('/admin')->assertOk();
});

it('counts a cost once in the monthly chart when a day has two sessions', function () {
    // Nikah + sanding on one day is two booking_dates rows; joining them
    // directly used to double every cost in the chart.
    $booking = Booking::factory()->create(['status' => BookingStatus::Confirmed]);
    BookingDate::factory()->for($booking)->on(today()->toDateString(), SessionSlot::Morning)->create();
    BookingDate::factory()->for($booking)->on(today()->toDateString(), SessionSlot::Afternoon)->create();
    BookingCost::factory()->of(50000)->create(['booking_id' => $booking->id]);

    $series = FinanceReport::for(now()->startOfMonth(), now()->endOfMonth(), FinanceReport::BASIS_CASH)->monthlySeries();

    expect(end($series['costs']))->toBe(500.0);
});

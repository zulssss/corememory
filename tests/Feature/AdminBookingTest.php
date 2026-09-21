<?php

declare(strict_types=1);

use App\Actions\Bookings\ChangeBookingStatus;
use App\Actions\Bookings\ConfirmBooking;
use App\Enums\BookingStatus;
use App\Enums\SessionSlot;
use App\Exceptions\SlotUnavailableException;
use App\Filament\Pages\FinanceDashboard;
use App\Filament\Resources\AddOns\AddOnResource;
use App\Filament\Resources\BlockedDates\BlockedDateResource;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Packages\PackageResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Testimonials\TestimonialResource;
use App\Models\AddOn;
use App\Models\BlockedDate;
use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\Package;
use App\Models\Post;
use App\Models\Project;
use App\Models\SlotHold;
use App\Models\Testimonial;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->create();
    $this->owner->assignRole('admin');
    $this->actingAs($this->owner);

    $this->date = now()->addMonths(6)->startOfDay()->toDateString();
});

function bookingFor(string $date, SessionSlot $slot = SessionSlot::Morning): Booking
{
    $booking = Booking::factory()->create();
    BookingDate::factory()->for($booking)->on($date, $slot)->create();

    return $booking;
}

describe('resource pages', function () {
    it('loads every Phase 3 list and create page', function (string $resource) {
        $this->get($resource::getUrl('index'))->assertOk();
        $this->get($resource::getUrl('create'))->assertOk();
    })->with([
        BookingResource::class,
        PackageResource::class,
        AddOnResource::class,
        BlockedDateResource::class,
    ]);

    /*
     * Edit pages get their own test because they are the only place a form
     * has to HYDRATE an existing record. Two real bugs hid here: an enum
     * formatter that assumed a cast object, and Filament's numeric() state
     * cast calling floatval() on a Money value object. Neither could be
     * reached by the create page, which starts empty.
     */
    it('loads the edit page for every record type', function (string $factory, string $resource) {
        $record = $factory::factory()->create();

        $this->get($resource::getUrl('edit', ['record' => $record]))->assertOk();
    })->with([
        [Booking::class, BookingResource::class],
        [Package::class, PackageResource::class],
        [AddOn::class, AddOnResource::class],
        [BlockedDate::class, BlockedDateResource::class],
        [Project::class, ProjectResource::class],
        [Testimonial::class, TestimonialResource::class],
        [Post::class, PostResource::class],
    ]);
});

describe('changing status keeps the calendar in step', function () {
    it('holds the date when confirmed', function () {
        $booking = bookingFor($this->date);

        app(ChangeBookingStatus::class)->handle($booking, BookingStatus::Confirmed, $this->owner);

        expect(SlotHold::count())->toBe(1)
            ->and($booking->fresh()->status)->toBe(BookingStatus::Confirmed);
    });

    it('releases the date when cancelled', function () {
        $booking = bookingFor($this->date);
        app(ConfirmBooking::class)->handle($booking);
        expect(SlotHold::count())->toBe(1);

        app(ChangeBookingStatus::class)->handle($booking, BookingStatus::Cancelled, $this->owner);

        // The most expensive bug this system could have is a cancelled wedding
        // that silently keeps its Saturday.
        expect(SlotHold::count())->toBe(0);
    });

    it('releases the date when moved back to quoted', function () {
        $booking = bookingFor($this->date);
        app(ConfirmBooking::class)->handle($booking);

        app(ChangeBookingStatus::class)->handle($booking, BookingStatus::Quoted, $this->owner);

        expect(SlotHold::count())->toBe(0);
    });

    it('refuses to confirm onto a slot another booking already holds', function () {
        $first = bookingFor($this->date);
        app(ConfirmBooking::class)->handle($first);

        $second = bookingFor($this->date);

        expect(fn () => app(ChangeBookingStatus::class)
            ->handle($second, BookingStatus::Confirmed, $this->owner))
            ->toThrow(SlotUnavailableException::class);

        expect($second->fresh()->status)->toBe(BookingStatus::Pending);
    });

    it('writes a note recording who changed what', function () {
        $booking = bookingFor($this->date);

        app(ChangeBookingStatus::class)->handle($booking, BookingStatus::Confirmed, $this->owner);

        $note = $booking->fresh()->notes()->first();

        expect($note->is_system)->toBeTrue()
            ->and($note->user_id)->toBe($this->owner->id)
            ->and($note->body)->toContain('Confirmed');
    });
});

describe('quick actions', function () {
    it('builds a WhatsApp link with the couple and reference pre-filled', function () {
        $booking = Booking::factory()->create([
            'phone' => '012-345 6789',
            'partner_one_name' => 'Aisyah',
        ]);

        $url = $booking->whatsappUrl();

        // A leading 0 becomes the 60 country code, spaces and dashes stripped.
        expect($url)->toContain('wa.me/60123456789')
            ->and(urldecode($url))->toContain('Aisyah')
            ->and(urldecode($url))->toContain($booking->reference);
    });

    it('returns no WhatsApp link when there is no phone number', function () {
        $booking = Booking::factory()->create(['phone' => '']);

        expect($booking->whatsappUrl())->toBeNull();
    });
});

describe('permissions', function () {
    it('lets staff reach bookings', function () {
        $staff = User::factory()->create();
        $staff->assignRole('staff');

        $this->actingAs($staff)
            ->get(BookingResource::getUrl('index'))
            ->assertOk();
    });
});

describe('finance is owner-only', function () {
    it('keeps staff out of invoices and the finance dashboard', function () {
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $this->actingAs($staff);

        // Staff work bookings, not the books.
        expect(InvoiceResource::canAccess())->toBeFalse()
            ->and(FinanceDashboard::canAccess())->toBeFalse();
    });

    it('lets the owner into invoices and the finance dashboard', function () {
        $this->actingAs($this->owner);

        expect(InvoiceResource::canAccess())->toBeTrue()
            ->and(FinanceDashboard::canAccess())->toBeTrue();
    });

    it('loads the finance dashboard', function () {
        $this->actingAs($this->owner);

        $this->get(FinanceDashboard::getUrl())->assertOk();
    });
});

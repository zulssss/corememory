<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\SessionSlot;
use App\Models\User;
use App\ValueObjects\Money;
use Database\Seeders\RoleSeeder;

/*
| Phase 1 smoke tests. These prove the foundation is wired correctly before any
| business logic is built on top of it.
*/

it('renders every public page', function (string $route) {
    $this->get(route($route))->assertOk();
})->with(['home', 'work', 'packages', 'availability', 'book', 'about', 'journal', 'contact']);

it('keeps the admin panel behind authentication', function () {
    $this->get('/admin')->assertRedirect();
});

it('lets a seeded studio user reach the admin panel', function (string $role) {
    // RefreshDatabase rolls the database back between tests, so the roles the
    // seeder created are gone by the time this runs — seed them explicitly.
    $this->seed(RoleSeeder::class);

    $user = User::factory()->create();
    $user->assignRole($role);

    expect($user->canAccessPanel(Filament\Facades\Filament::getPanel('admin')))->toBeTrue();
})->with(['super_admin', 'admin', 'staff']);

it('keeps a user with no role out of the admin panel', function () {
    $user = User::factory()->create();

    expect($user->canAccessPanel(Filament\Facades\Filament::getPanel('admin')))->toBeFalse();
});

it('runs on Malaysian time', function () {
    expect(config('app.timezone'))->toBe('Asia/Kuala_Lumpur');
});

describe('Money', function () {
    it('never loses a sen to floating point', function () {
        // 0.1 + 0.2 in float is 0.30000000000000004; in cents it is exactly 30.
        $total = Money::fromRinggit(0.1)->plus(Money::fromRinggit(0.2));

        expect($total)->toBeMoney(30);
    });

    it('formats as Malaysian ringgit', function () {
        expect((new Money(380000))->format())->toBe('RM 3,800.00')
            ->and((new Money(380000))->formatCompact())->toBe('RM 3,800')
            ->and((new Money(380050))->formatCompact())->toBe('RM 3,800.50');
    });

    it('splits a deposit and balance that add back to the total', function () {
        $total = new Money(680000);
        $deposit = $total->percent(30);
        $balance = $total->minus($deposit);

        expect($deposit)->toBeMoney(204000)
            ->and($deposit->plus($balance))->toBeMoney($total->cents);
    });

    it('multiplies by a quantity for add-ons like extra hours', function () {
        expect((new Money(25000))->times(3))->toBeMoney(75000);
    });
});

describe('SessionSlot expansion', function () {
    it('expands full day into the three concrete slots', function () {
        expect(SessionSlot::FullDay->expand())->toBe(SessionSlot::concrete())
            ->and(SessionSlot::FullDay->expand())->toHaveCount(3);
    });

    it('expands a single slot to just itself', function () {
        expect(SessionSlot::Morning->expand())->toBe([SessionSlot::Morning]);
    });

    it('never treats full day as a concrete slot', function () {
        // This is what lets one UNIQUE(event_date, session_slot) index enforce
        // every conflict rule. If full_day ever appears here, the index breaks.
        expect(SessionSlot::concrete())->not->toContain(SessionSlot::FullDay);
    });
});

describe('BookingStatus', function () {
    it('blocks a slot only for confirmed and deposit_paid by default', function () {
        expect(BookingStatus::Confirmed->blocksSlot())->toBeTrue()
            ->and(BookingStatus::DepositPaid->blocksSlot())->toBeTrue()
            ->and(BookingStatus::Pending->blocksSlot())->toBeFalse()
            ->and(BookingStatus::Cancelled->blocksSlot())->toBeFalse();
    });

    it('treats pending as tentative, not held', function () {
        expect(BookingStatus::Pending->isTentative())->toBeTrue()
            ->and(BookingStatus::Pending->blocksSlot())->toBeFalse();
    });

    it('moves the hard guarantee to submit time when pending is made blocking', function () {
        // The brief requires blocking statuses to be configurable. Proving the
        // switch works means Phase 3 needs no code change to flip the policy.
        config()->set('booking.blocking_statuses', ['pending', 'confirmed', 'deposit_paid']);

        expect(BookingStatus::Pending->blocksSlot())->toBeTrue();
    });
});

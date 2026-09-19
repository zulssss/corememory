<?php

declare(strict_types=1);

use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Testimonials\TestimonialResource;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

/** Signs in as a user holding the given role. */
function admin(string $role = 'admin'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);
    test()->actingAs($user);

    return $user;
}

it('loads every resource list page', function (string $resource) {
    admin();

    $this->get($resource::getUrl('index'))->assertOk();
})->with([
    ProjectResource::class,
    TestimonialResource::class,
    PostResource::class,
]);

it('loads every resource create page', function (string $resource) {
    admin();

    $this->get($resource::getUrl('create'))->assertOk();
})->with([
    ProjectResource::class,
    TestimonialResource::class,
    PostResource::class,
]);

it('loads the settings page for the owner', function () {
    admin('admin');

    $this->get(ManageSettings::getUrl())->assertOk();
});

it('keeps staff out of the settings page', function () {
    admin('staff');

    // Staff work bookings but must never change pricing, terms or the
    // business details printed on invoices.
    expect(ManageSettings::canAccess())->toBeFalse();
});

it('lets super_admin and admin into settings', function (string $role) {
    admin($role);

    expect(ManageSettings::canAccess())->toBeTrue();
})->with(['super_admin', 'admin']);

<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|------------------------------------------------------------------------------
| Test Case
|------------------------------------------------------------------------------
| Every Feature test gets the application TestCase and a fresh database.
|
| RefreshDatabase wraps each test in a transaction and rolls it back. NOTE for
| Phase 3: the concurrent double-booking tests must NOT use RefreshDatabase,
| because they need two genuinely separate connections committing against the
| same table. Those tests opt into DatabaseTruncation instead — see the comment
| in that test file when it lands.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/*
|------------------------------------------------------------------------------
| Custom Expectations
|------------------------------------------------------------------------------
*/

/** expect($money)->toBeMoney(380000) — reads better than ->cents->toBe(). */
expect()->extend('toBeMoney', function (int $cents) {
    expect($this->value->cents)->toBe($cents);

    return $this;
});

/*
|------------------------------------------------------------------------------
| Helpers
|------------------------------------------------------------------------------
*/

/** A signed-in user with the given role, for admin panel tests. */
function actingAsRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    test()->actingAs($user);

    return $user;
}

<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Seeds the three studio roles and one demo user for each, so a fresh
 * `migrate:fresh --seed` gives you a panel you can actually log into.
 *
 * Passwords here are deliberately obvious and local-only. Production users are
 * created by hand — never seed a real credential.
 */
class RoleSeeder extends Seeder
{
    /**
     * Role => the demo account created for it.
     *
     * @var array<string, array{name: string, email: string}>
     */
    private const ACCOUNTS = [
        'super_admin' => ['name' => 'Super Admin', 'email' => 'super@corememory.test'],
        'admin' => ['name' => 'Studio Owner', 'email' => 'owner@corememory.test'],
        'staff' => ['name' => 'Studio Staff', 'email' => 'staff@corememory.test'],
    ];

    public function run(): void
    {
        foreach (self::ACCOUNTS as $roleName => $account) {
            // firstOrCreate so re-seeding on top of an existing database is safe.
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $user = User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([$role]);
        }
    }
}

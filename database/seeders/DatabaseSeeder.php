<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * `php artisan migrate:fresh --seed` must always produce a fully populated,
 * demo-ready site — realistic bookings, invoices and costs — so the dashboard
 * has something real to draw. Seeders are added here as each phase lands.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,       // Phase 1 — roles + one demo user each
            // Phase 2 — SettingsSeeder, ProjectSeeder, TestimonialSeeder, PostSeeder
            // Phase 3 — PackageSeeder, AddOnSeeder, BookingSeeder
            // Phase 4 — InvoiceSeeder, PaymentSeeder, BookingCostSeeder
        ]);
    }
}

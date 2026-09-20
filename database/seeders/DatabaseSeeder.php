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
            RoleSeeder::class,          // Phase 1 — roles + one demo user each
            SettingsSeeder::class,      // Phase 2 — owner-editable site content
            ProjectSeeder::class,       //           portfolio + placeholder media
            TestimonialSeeder::class,   //           linked to the seeded projects
            PostSeeder::class,          //           journal
            PackageSeeder::class,       // Phase 3 — pricing
            AddOnSeeder::class,
            BookingSeeder::class,       //           demo enquiries across the funnel
            // Phase 4 — InvoiceSeeder, PaymentSeeder, BookingCostSeeder
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\Settings;
use Illuminate\Database\Seeder;

/**
 * Seeds the owner-editable settings with demo values.
 *
 * Everything here is placeholder content, replaced by the studio in
 * Admin → Settings. No real contact details, SSM number or bank account.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        Settings::setMany([
            'hero.headline' => 'We photograph weddings the way they actually feel — unhurried, honest, and yours.',
            'hero.location' => 'Kuala Lumpur, Malaysia',
            'statement.eyebrow' => 'Based in Kuala Lumpur, working across Malaysia',
            'statement.lead' => 'At CoreMemory, we believe a wedding photograph should outlive the day it was taken.',
            'statement.rest' => 'So we publish our prices openly, we tell you what is included before you ask, and we spend our time photographing your day rather than negotiating it.',
        ], group: 'brand');

        Settings::setMany([
            'stats.years' => 5,
            'stats.years_label' => 'Years of experience',
            'stats.weddings' => 240,
            'stats.weddings_label' => 'Weddings photographed',
            'stats.awards' => 12,
            'stats.awards_label' => 'Awards received',
            'stats.couples' => 480,
            'stats.couples_label' => 'Happy couples',
        ], group: 'brand');

        Settings::setMany([
            'contact.email' => 'hello@corememory.test',
            'contact.phone' => '+60 12-345 6789',
            'contact.whatsapp' => '60123456789',
            'contact.address' => "Lot 12, Jalan Placeholder\n50450 Kuala Lumpur",
            'social.instagram' => 'https://instagram.com/',
            'social.tiktok' => 'https://tiktok.com/',
        ], group: 'contact');

        Settings::setMany([
            'booking.deposit_percent' => 30,
            'booking.payment_terms' => 'A 30% deposit confirms your date. The balance is due 7 days before the event.',
            'booking.cancellation_policy' => 'Deposits are non-refundable. Dates may be moved once, subject to availability.',
        ], group: 'booking');

        Settings::setMany([
            'seo.title' => 'CoreMemory — Wedding photography in Malaysia',
            'seo.description' => 'Wedding photography and videography in Malaysia. Transparent pricing, published packages, and a booking process that starts with your date.',
        ], group: 'seo');

        // Placeholder business details. The studio enters real values before
        // issuing any invoice — an invoice with a fake SSM number must never
        // reach a client.
        Settings::setMany([
            'invoice.registered_name' => 'CoreMemory Studio (Placeholder) Sdn Bhd',
            'invoice.ssm_number' => '000000000000',
            'invoice.address' => "Lot 12, Jalan Placeholder\n50450 Kuala Lumpur",
            'invoice.bank_name' => 'Placeholder Bank',
            'invoice.bank_account_name' => 'CoreMemory Studio',
            'invoice.bank_account_number' => '0000 0000 0000',
        ], group: 'invoice');
    }
}

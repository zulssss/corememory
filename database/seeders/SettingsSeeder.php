<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\Settings;
use Illuminate\Database\Seeder;

/**
 * Seeds the owner-editable settings.
 *
 * The marketing copy and booking terms are CoreMemory's own words, taken from
 * the published pricelist with only clear misspellings corrected.
 *
 * STILL PLACEHOLDER, because the pricelist does not state them: the contact
 * email and phone, the TikTok URL, and every invoice.* value. The pricelist
 * gives only an Instagram handle. An invoice carrying the fake SSM number
 * below must never reach a client.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        Settings::setMany([
            'hero.headline' => 'We believe moment cannot be recreated, and words cannot describe like photograph does.',
            'hero.location' => 'Malaysia',
            'statement.eyebrow' => 'We are happy to travel anywhere to document your story',
            'statement.lead' => 'As a visual storyteller, we value the simplicity of raw emotion and candid intimacy.',
            'statement.rest' => 'Your story is the heart of our photograph. That is why we are committed to tell the story of each wedding for you to remember. From the grand scenes to the gentle touch. Let\'s start on a journey and create something beautiful together.',
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
            'contact.email' => '',        // not on the pricelist
            'contact.phone' => '',        // not on the pricelist
            'contact.whatsapp' => '',     // not on the pricelist
            'contact.address' => '',      // not on the pricelist
            'social.instagram' => 'https://instagram.com/corememoryy_',
            'social.tiktok' => '',   // not given on the pricelist
        ], group: 'contact');

        Settings::setMany([
            'booking.deposit_per_event_cents' => 10000,
            'booking.payment_terms' => 'Prices are as stated and your date is locked by deposit. A RM100 deposit per event locks the date. Full payment of the balance is due 2 days before the event.',
            'booking.cancellation_policy' => 'Deposits are non-refundable. A deposit can be carried forward, depending on availability, or changed to another type of shoot. Any cancellation must be made 1-2 months before the event.',
        ], group: 'booking');

        Settings::setMany([
            'seo.title' => 'CoreMemory — Wedding photography and videography in Malaysia',
            'seo.description' => 'Wedding photography and videography in Malaysia. Every price published openly, from RM900 sessions to full photo and video coverage. We are happy to travel anywhere to document your story.',
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

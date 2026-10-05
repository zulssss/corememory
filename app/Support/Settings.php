<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\Cache;

/**
 * Owner-editable site settings, read from the `settings` table.
 *
 * Read through this class, never the Setting model directly: the whole table is
 * loaded in ONE query and cached, so a page that reads twenty settings still
 * costs a single database hit. Saving busts the cache.
 *
 *     Settings::get('hero.headline')
 *     Settings::set('hero.headline', 'New copy', group: 'brand')
 *
 * Structural rules (booking window, blocking statuses) belong in
 * config/booking.php instead — those are a developer's decision, not the
 * owner's. Anything the studio owner should be able to change without calling
 * a developer belongs here.
 */
final class Settings
{
    private const CACHE_KEY = 'settings.all';

    /**
     * Fallbacks used when a key has never been saved. This is what makes the
     * site render correctly on a fresh install before any seeding, and it
     * documents every available key in one place.
     *
     * @var array<string, mixed>
     */
    public const DEFAULTS = [
        // Brand and homepage copy
        'hero.headline' => null,
        'hero.location' => 'Kuala Lumpur, Malaysia',
        'statement.lead' => null,
        'statement.rest' => null,
        'statement.eyebrow' => null,

        // The four stat cards
        'stats.years' => 5,
        'stats.years_label' => 'Years of experience',
        'stats.weddings' => 240,
        'stats.weddings_label' => 'Weddings photographed',
        'stats.awards' => 12,
        'stats.awards_label' => 'Awards received',
        'stats.couples' => 480,
        'stats.couples_label' => 'Happy couples',

        // Contact
        'contact.email' => null,
        'contact.phone' => null,
        'contact.whatsapp' => null,
        'contact.address' => null,

        // Social
        'social.instagram' => null,
        'social.tiktok' => null,

        // Commercial terms
        'booking.deposit_per_event_cents' => null,   // falls back to config/booking.php
        'booking.payment_terms' => null,
        'booking.cancellation_policy' => null,

        // SEO
        'seo.title' => null,
        'seo.description' => null,

        // Business details printed on invoices
        'invoice.registered_name' => null,
        'invoice.ssm_number' => null,
        'invoice.address' => null,
        'invoice.bank_name' => null,
        'invoice.bank_account_name' => null,
        'invoice.bank_account_number' => null,
    ];

    /**
     * Request-level memo.
     *
     * Without this, every Settings::get() is a round trip to the cache store.
     * The homepage reads ~8 settings, which meant 8 cache queries per request
     * against the database cache driver — and 8 Redis round trips in
     * production. The cache still does the cross-request work; this just stops
     * us asking it the same question repeatedly inside one request.
     *
     * @var array<string, mixed>|null
     */
    private static ?array $memo = null;

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return self::$memo ??= Cache::rememberForever(
            self::CACHE_KEY,
            fn () => Setting::query()->pluck('value', 'key')->all(),
        );
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::all()[$key] ?? null;

        if ($value !== null && $value !== '') {
            return $value;
        }

        // An explicit $default beats the DEFAULTS table, so a caller can say
        // "use this instead" at the call site.
        return $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function set(string $key, mixed $value, string $group = 'general'): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group],
        );

        self::flush();
    }

    /** @param  array<string, mixed>  $values */
    public static function setMany(array $values, string $group = 'general'): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        }

        self::flush();
    }

    public static function flush(): void
    {
        self::$memo = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * The flat deposit charged per event, preferring the owner's setting.
     *
     * Per EVENT, not per booking: the published terms price it that way, so a
     * solemnisation plus a reception is two deposits.
     */
    public static function depositPerEvent(): Money
    {
        return new Money((int) (self::get('booking.deposit_per_event_cents')
            ?? config('booking.default_deposit_per_event_cents')));
    }
}

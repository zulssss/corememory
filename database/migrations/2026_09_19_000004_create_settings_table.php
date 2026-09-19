<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner-editable settings: hero copy, stat numbers, contact details, deposit
 * percentage, invoice business details.
 *
 * A key/value table rather than a single JSON singleton row, because Filament
 * saves one field at a time and a key/value shape means two admins editing
 * different fields can't clobber each other's work.
 *
 * Deliberately NOT spatie/laravel-settings — that package is not on the
 * approved list, and a keyed table plus a cached accessor covers everything
 * the brief asks for. See app/Support/Settings.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();

            // JSON so a setting can hold a string, a number, or a structure
            // like the four stat cards, without needing a column per type.
            $table->json('value')->nullable();

            // Groups the admin form into sections: brand, contact, seo, invoice.
            $table->string('group', 32)->default('general')->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};

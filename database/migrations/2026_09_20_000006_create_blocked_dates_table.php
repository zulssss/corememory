<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dates the studio takes off the calendar by hand — leave, a public holiday, a
 * wedding booked offline.
 *
 * This is the admin-facing record. Saving one writes the expanded rows into
 * slot_holds, so a manual block and a confirmed booking cannot occupy the same
 * slot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocked_dates', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();

            // Null = the whole day (equivalent to full_day).
            $table->string('session_slot', 16)->nullable();

            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_dates');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One date + session slot a booking covers. A booking has at least one, and a
 * two-day wedding (nikah + reception) has two, each with its own venue.
 *
 * session_slot here may be 'full_day' — this is the couple's CHOICE. It is
 * expanded into concrete slots when written to slot_holds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();

            $table->date('event_date')->index();
            $table->string('session_slot', 16);

            // "Nikah", "Reception" — shown on the summary and the invoice.
            $table->string('label')->nullable();

            $table->string('venue')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();

            $table->timestamps();

            // A single booking must not list the same date and slot twice.
            $table->unique(['booking_id', 'event_date', 'session_slot'], 'booking_dates_unique_per_booking');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_dates');
    }
};

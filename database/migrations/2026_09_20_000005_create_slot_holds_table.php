<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * THE TABLE THAT PREVENTS DOUBLE BOOKINGS.
 *
 * Read app/Services/AvailabilityService.php and CLAUDE.md § Availability
 * before changing anything here.
 *
 * Only CONCRETE slots are stored — morning, afternoon, evening. 'full_day' is
 * never a row: it expands into three. That single decision is what lets one
 * plain unique index enforce every conflict rule with no application logic:
 *
 *   Full Day onto an existing Morning  -> collides on the morning row
 *   Morning onto an existing Full Day  -> collides on the morning row
 *   The same slot twice                -> collides
 *   Morning + Evening on one date      -> both fit, correctly
 *
 * Rows exist ONLY for blocking statuses (config/booking.php). A pending
 * enquiry writes nothing, which is what makes it tentative rather than held.
 * MySQL has no partial indexes — that is precisely why holds live in their own
 * table instead of being an index on bookings.
 *
 * holdable is a BookingDate or a BlockedDate: an admin block and a confirmed
 * booking share one uniqueness namespace, so they collide with each other too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slot_holds', function (Blueprint $table) {
            $table->id();

            $table->date('event_date');
            $table->string('session_slot', 16);

            $table->morphs('holdable');

            $table->string('reason')->nullable();
            $table->timestamps();

            // The lock. Two concurrent transactions cannot both insert this
            // pair — one gets MySQL error 1062 and is rejected.
            $table->unique(['event_date', 'session_slot'], 'slot_holds_date_slot_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slot_holds');
    }
};

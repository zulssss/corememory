<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A booking is an ENQUIRY until the studio confirms it. There is no event_date
 * column here — dates live in booking_dates, because a Malaysian wedding is
 * routinely a nikah on one day and a reception on another, at two venues.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();

            // CM-2026-0001. Allocated from the sequences table inside the
            // create transaction, so it is sequential and never reused.
            $table->string('reference', 32)->unique();

            $table->foreignId('package_id')->nullable()->constrained()->nullOnDelete();

            // Couple
            $table->string('partner_one_name');
            $table->string('partner_two_name')->nullable();
            $table->string('email');
            $table->string('phone', 32);

            $table->unsignedInteger('guest_count')->nullable();
            $table->string('source', 32)->nullable()->index();
            $table->text('notes')->nullable();

            /*
             * Prices are CAPTURED at booking time. Changing a package price
             * later must never rewrite an existing enquiry or a past invoice.
             */
            $table->unsignedInteger('subtotal_cents')->default(0);
            $table->unsignedInteger('addons_total_cents')->default(0);
            $table->unsignedInteger('estimated_total_cents')->default(0);
            $table->unsignedInteger('deposit_cents')->default(0);

            $table->string('status', 24)->default('pending')->index();
            $table->text('admin_notes')->nullable();

            // When a pending enquiry was auto-cancelled by bookings:expire-stale.
            $table->timestamp('lapsed_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * General contact-form messages.
 *
 * Deliberately separate from `bookings`. A booking is a structured quotation
 * request with a date, a package and a price; this is "do you shoot in
 * Penang?". Forcing both through one table would mean a bookings list full of
 * rows with no date, which is exactly the noise this site exists to remove.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 32)->nullable();
            $table->text('message');
            $table->string('status', 24)->default('new')->index();
            $table->text('admin_notes')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiries');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_add_on', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('add_on_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('qty')->default(1);

            // The price when the couple booked, not today's price. This is what
            // makes an old enquiry and its invoice still add up correctly after
            // the studio raises prices.
            $table->unsignedInteger('price_cents_at_booking');
            $table->unsignedInteger('line_total_cents');

            $table->timestamps();

            $table->unique(['booking_id', 'add_on_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_add_on');
    }
};

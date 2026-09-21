<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a booking COST the studio to deliver.
 *
 * Profit is meaningless without this. Revenue alone tells the owner how busy
 * they are, not whether the work was worth doing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();

            $table->string('label');

            // Groups costs on the dashboard: crew, travel, equipment, printing…
            $table->string('category', 32)->default('other')->index();

            $table->unsignedInteger('amount_cents');

            // Whether the studio has actually paid this out yet.
            $table->boolean('is_paid')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_costs');
    }
};

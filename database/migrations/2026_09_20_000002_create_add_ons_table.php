<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('add_ons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('price_cents');
            $table->text('description')->nullable();

            /*
             * Quantifiable add-ons render a stepper instead of a checkbox.
             * "Extra hour" is naturally x3; a drone is not. max_qty keeps the
             * UI from offering nonsense like 40 extra hours.
             */
            $table->boolean('is_quantifiable')->default(false);
            $table->unsignedSmallInteger('max_qty')->default(1);

            // Null/empty = available with every package. Otherwise a list of
            // package ids this add-on applies to.
            $table->json('applies_to')->nullable();

            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('add_ons');
    }
};

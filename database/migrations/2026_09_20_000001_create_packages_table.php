<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();

            // Integer cents. Never a float — see App\ValueObjects\Money.
            $table->unsignedInteger('price_cents');

            // true renders "From RM 3,800" rather than a fixed price.
            $table->boolean('price_is_from')->default(false);

            $table->unsignedSmallInteger('duration_hours')->nullable();

            // What the couple actually gets: ["8 hours coverage", "300 edited
            // photos", ...]. JSON because inclusions are only ever read back
            // with their own package, never queried across packages.
            $table->json('inclusions')->nullable();

            $table->text('description')->nullable();
            $table->boolean('is_popular')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};

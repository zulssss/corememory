<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('couple_name');
            $table->text('quote');

            // Context line above the quote, e.g. "Wedding, 2026".
            $table->string('context')->nullable();

            // Optional link to the wedding it came from. nullOnDelete so
            // removing a project never silently deletes a real testimonial.
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();

            $table->boolean('is_published')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};

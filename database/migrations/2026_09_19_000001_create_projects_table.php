<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projects are the wedding stories shown on /work.
 *
 * Images are NOT columns here — they live in the media library (the `media`
 * table), attached to this model. That is what gives drag-to-reorder galleries
 * and automatic WebP conversions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('category', 32)->index();

            $table->string('couple_names')->nullable();
            $table->date('event_date')->nullable();
            $table->string('venue')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();

            $table->text('excerpt')->nullable();
            $table->longText('description')->nullable();

            // Crew credits: [{"role": "Photographer", "name": "Zul"}, ...]
            // JSON rather than a table because credits are never queried across
            // projects — they are only ever read back with their own project.
            $table->json('crew')->nullable();

            $table->boolean('is_featured')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();

            // Null = draft. Drafts are invisible on the public site.
            $table->timestamp('published_at')->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};

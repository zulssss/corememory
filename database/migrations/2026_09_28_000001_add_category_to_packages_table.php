<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The real pricelist has eighteen packages across three coverage types plus
 * standalone sessions. Without a category the /packages page can only render
 * one flat list and an eighteen-column comparison table, which is unreadable.
 *
 * Nullable so existing rows survive the migration; the seeder backfills it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->string('category')->nullable()->after('slug')->index();
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }
};

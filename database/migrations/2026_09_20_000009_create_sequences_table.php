<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sequential counters for booking references and invoice numbers.
 *
 * A dedicated row that is locked (SELECT ... FOR UPDATE) and incremented inside
 * the same transaction as the record it numbers.
 *
 * Deliberately NOT count() + 1 — that reuses a number after any delete, and two
 * simultaneous requests both read the same count and produce a duplicate. The
 * brief requires "sequential, never reused", and only a locked counter gives
 * that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 32);      // 'booking' | 'invoice'
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['scope', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};

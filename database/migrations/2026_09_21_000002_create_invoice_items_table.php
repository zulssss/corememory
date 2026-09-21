<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            $table->string('description');
            $table->unsignedSmallInteger('qty')->default(1);
            // Signed: deduction lines (deposit already paid, balance deferred)
            // are negative, which is how invoices normally express a credit.
            $table->integer('unit_price_cents');

            // Stored rather than derived, so a line that was discounted or
            // hand-adjusted keeps exactly the figure that was printed.
            $table->integer('total_cents');

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};

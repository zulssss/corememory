<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money actually received, as opposed to money invoiced. The dashboard never
 * conflates the two.
 *
 * The gateway columns are groundwork only — Phase 1 has no online payments.
 * They exist so ToyyibPay, Billplz or Stripe can be added later by writing a
 * webhook handler, without a schema migration or a refactor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('amount_cents');
            $table->date('paid_at')->index();

            $table->string('method', 24);          // bank_transfer|cash|ewallet|card
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();

            // Future online payments.
            $table->string('gateway', 32)->nullable();
            $table->string('gateway_reference')->nullable();
            $table->json('raw_response')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

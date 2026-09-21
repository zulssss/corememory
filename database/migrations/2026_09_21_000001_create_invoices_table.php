<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An invoice is a SNAPSHOT, not a live view of a booking.
 *
 * Line items are copied at the prices captured when the couple booked, and
 * once the invoice leaves draft they lock. Regenerating the PDF must never
 * change an issued number or a historical price — a client who has the PDF in
 * their inbox must be able to match it to what we hold.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            // CM-INV-2026-0001. From the locked sequences row, never reused.
            $table->string('number', 32)->unique();

            // Nullable: the studio can invoice a walk-in who never used the
            // website, so an invoice does not require a booking.
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type', 16)->default('deposit');   // deposit|final|custom

            /*
             * Client details are COPIED onto the invoice rather than read
             * through the booking. A couple who later corrects their name must
             * not silently rewrite an invoice already sent to them.
             */
            $table->string('client_name');
            $table->string('client_email')->nullable();
            $table->string('client_phone', 32)->nullable();
            $table->text('client_address')->nullable();

            $table->date('issued_at')->nullable();
            $table->date('due_at')->nullable()->index();

            /*
             * SIGNED integers, not unsigned. A deposit invoice carries a
             * "less: balance payable after the event" line and a final invoice
             * carries "less: deposit already invoiced" — both negative. Keeping
             * these signed means subtotal + deductions = total is always plain
             * arithmetic, with no special-casing.
             */
            $table->integer('subtotal_cents')->default(0);
            $table->integer('discount_cents')->default(0);
            $table->integer('total_cents')->default(0);

            $table->string('status', 16)->default('draft')->index();

            // Where the rendered PDF lives on disk.
            $table->string('pdf_path')->nullable();

            $table->text('notes')->nullable();

            /*
             * Set when the invoice leaves draft. After this, line items are
             * read-only — an invoice a client has seen must not change under
             * them.
             */
            $table->timestamp('locked_at')->nullable();

            // A final invoice points at its deposit so it can show it as a
            // deduction and the two always agree.
            $table->foreignId('deposit_of_invoice_id')->nullable()
                ->constrained('invoices')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

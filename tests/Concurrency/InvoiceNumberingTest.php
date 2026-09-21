<?php

declare(strict_types=1);

use App\Actions\Invoices\GenerateInvoice;
use App\Enums\InvoiceType;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Sequence;
use Illuminate\Support\Facades\DB;

/*
|------------------------------------------------------------------------------
| INVOICE NUMBERS ARE SEQUENTIAL AND NEVER REUSED
|------------------------------------------------------------------------------
| The ceritaconvo repo derives its number from Invoice::count() + 1. That is
| wrong in two ways, and these tests pin both:
|
|   1. Delete an invoice and the next one REUSES a number that has already been
|      sent to a client.
|   2. Two simultaneous requests both read the same count and produce a
|      duplicate — which the unique index then rejects, losing one invoice.
|
| Ours takes the number from a locked sequences row inside the transaction.
*/

it('never reuses a number after a delete', function () {
    $booking = Booking::factory()->create();

    $first = app(GenerateInvoice::class)->handle($booking, InvoiceType::Deposit);
    $second = app(GenerateInvoice::class)->handle($booking, InvoiceType::Final);

    $second->delete();

    $third = app(GenerateInvoice::class)->handle($booking, InvoiceType::Custom);

    // count()+1 would hand out 0002 again here.
    expect($third->number)->toBe('CM-INV-'.now()->year.'-0003')
        ->and($third->number)->not->toBe($second->number);
});

it('gives every invoice a unique number across many allocations', function () {
    $bookings = Booking::factory()->count(15)->create();

    $numbers = $bookings->map(
        fn (Booking $booking) => app(GenerateInvoice::class)
            ->handle($booking, InvoiceType::Deposit)->reference ?? null
    );

    $all = Invoice::pluck('number');

    expect($all)->toHaveCount(15)
        ->and($all->unique())->toHaveCount(15);
});

it('serialises two connections allocating at the same moment', function () {
    // The sequence row is locked with SELECT ... FOR UPDATE inside a
    // transaction, so the second connection waits rather than reading a stale
    // counter.
    config(['database.connections.racer' => config('database.connections.mysql')]);
    $second = DB::connection('racer');
    $second->statement('SET SESSION innodb_lock_wait_timeout = 3');

    $year = (int) now()->year;

    DB::beginTransaction();
    $a = Sequence::next('invoice', $year);

    $blocked = false;
    try {
        // Same row, different connection: must block on the lock A holds.
        $second->table('sequences')
            ->where('scope', 'invoice')->where('year', $year)
            ->lockForUpdate()->first();
    } catch (Throwable) {
        $blocked = true;
    }

    DB::commit();

    $b = Sequence::next('invoice', $year);

    expect($blocked)->toBeTrue()
        ->and($b)->toBe($a + 1);

    DB::disconnect('racer');
});

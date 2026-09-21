<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Money as MoneyCast;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

/**
 * An invoice.
 *
 * It is a SNAPSHOT. Line items are copied at the prices captured when the
 * couple booked, and lock once the invoice leaves draft. Client details are
 * copied too, so a later correction to a booking never rewrites an invoice
 * already sitting in someone's inbox.
 *
 * Create these through App\Actions\Invoices\GenerateInvoice, which allocates
 * the number inside a transaction.
 */
class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'number', 'booking_id', 'type',
        'client_name', 'client_email', 'client_phone', 'client_address',
        'issued_at', 'due_at',
        'subtotal_cents', 'discount_cents', 'total_cents',
        'status', 'pdf_path', 'notes', 'locked_at', 'deposit_of_invoice_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => InvoiceType::class,
            'status' => InvoiceStatus::class,
            'issued_at' => 'date',
            'due_at' => 'date',
            'subtotal_cents' => MoneyCast::class,
            'discount_cents' => MoneyCast::class,
            'total_cents' => MoneyCast::class,
            'locked_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('paid_at');
    }

    /** The deposit invoice this final invoice follows, if any. */
    public function depositInvoice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'deposit_of_invoice_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Money
    |--------------------------------------------------------------------------
    */

    /**
     * What has actually been received. Never confuse this with the total.
     *
     * NOTE the closure. `$collection->sum('amount_cents')` would pull the
     * CAST attribute — a Money object — and try to add objects together,
     * which is a fatal error. With a Money cast, always sum ->cents
     * explicitly. (Query-builder sums like Payment::sum() are fine: those
     * happen in SQL and never touch the cast.)
     */
    public function paid(): Money
    {
        return new Money((int) $this->payments->sum(fn (Payment $payment) => $payment->amount_cents->cents));
    }

    /** What is still owed. */
    public function outstanding(): Money
    {
        $outstanding = $this->total_cents->minus($this->paid());

        // An overpayment shows as nothing owed rather than a negative balance.
        return $outstanding->isNegative() ? Money::zero() : $outstanding;
    }

    public function isSettled(): bool
    {
        return $this->paid()->cents >= $this->total_cents->cents;
    }

    /**
     * Overdue is COMPUTED, never stored.
     *
     * A stored flag needs a nightly job to stay true, and is wrong for every
     * hour between the due date passing and that job running.
     */
    public function isOverdue(): bool
    {
        return $this->status === InvoiceStatus::Sent
            && $this->due_at !== null
            && $this->due_at->isPast()
            && ! $this->isSettled();
    }

    /** How many days overdue, for the ageing buckets. */
    public function daysOverdue(): int
    {
        return $this->isOverdue() ? (int) $this->due_at->diffInDays(now()) : 0;
    }

    /** 0-30 / 31-60 / 60+, as the receivables breakdown reports it. */
    public function ageingBucket(): ?string
    {
        if (! $this->isOverdue()) {
            return null;
        }

        return match (true) {
            $this->daysOverdue() <= 30 => '0-30',
            $this->daysOverdue() <= 60 => '31-60',
            default => '60+',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Locking
    |--------------------------------------------------------------------------
    */

    /** Line items may only be edited while the invoice is still a draft. */
    public function isEditable(): bool
    {
        return $this->locked_at === null && $this->status->isEditable();
    }

    public function lock(): void
    {
        if ($this->locked_at === null) {
            $this->forceFill(['locked_at' => now()])->save();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Client access
    |--------------------------------------------------------------------------
    */

    /**
     * A signed, expiring download link.
     *
     * No login — a couple must be able to open it straight from an email — but
     * the URL carries a signature, so it cannot be guessed by walking invoice
     * ids and it stops working after the expiry.
     */
    public function downloadUrl(int $days = 30): string
    {
        return URL::temporarySignedRoute(
            'invoices.download',
            now()->addDays($days),
            ['invoice' => $this->getKey()],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeIssued(Builder $query): Builder
    {
        // Drafts are not revenue. Cancelled invoices are not revenue either.
        return $query->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Paid->value]);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', InvoiceStatus::Sent)
            ->whereNotNull('due_at')
            ->whereDate('due_at', '<', today());
    }

    public function scopeIssuedBetween(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('issued_at', [$from, $to]);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Money as MoneyCast;
use App\Enums\BookingStatus;
use App\Enums\EnquirySource;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A booking request.
 *
 * IMPORTANT: creating one of these is an ENQUIRY, not a confirmation. It does
 * not hold the date. Only a status in config('booking.blocking_statuses')
 * writes rows into slot_holds and takes the date off the calendar, and only
 * the studio moves it there.
 *
 * Never change status by assignment. Use the Actions:
 *   App\Actions\Bookings\ConfirmBooking  — writes holds, can fail on a clash
 *   App\Actions\Bookings\CancelBooking   — releases holds
 */
class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference', 'package_id', 'partner_one_name', 'partner_two_name',
        'email', 'phone', 'guest_count', 'source', 'notes',
        'subtotal_cents', 'addons_total_cents', 'estimated_total_cents',
        'deposit_cents', 'status', 'admin_notes', 'lapsed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'source' => EnquirySource::class,
            'subtotal_cents' => MoneyCast::class,
            'addons_total_cents' => MoneyCast::class,
            'estimated_total_cents' => MoneyCast::class,
            'deposit_cents' => MoneyCast::class,
            'lapsed_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function dates(): HasMany
    {
        return $this->hasMany(BookingDate::class)->orderBy('event_date');
    }

    public function addOns(): BelongsToMany
    {
        return $this->belongsToMany(AddOn::class, 'booking_add_on')
            ->withPivot(['qty', 'price_cents_at_booking', 'line_total_cents'])
            ->withTimestamps();
    }

    public function notes(): HasMany
    {
        return $this->hasMany(BookingNote::class)->latest();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->orderBy('issued_at');
    }

    /** What this booking cost the studio to deliver. */
    public function costs(): HasMany
    {
        return $this->hasMany(BookingCost::class);
    }

    /**
     * Total direct costs.
     *
     * Summed via a closure on ->cents: with the Money cast, sum('amount_cents')
     * would add Money OBJECTS and fatal. See Invoice::paid().
     */
    public function totalCosts(): Money
    {
        return new Money((int) $this->costs->sum(fn (BookingCost $cost) => $cost->amount_cents->cents));
    }

    /**
     * Gross profit against COLLECTED revenue, not invoiced.
     *
     * An invoice nobody has paid is not profit, however good it looks on the
     * booking. The dashboard can show the accrual view separately.
     */
    public function grossProfit(): Money
    {
        return $this->collectedRevenue()->minus($this->totalCosts());
    }

    /** Money actually received against this booking's invoices. */
    public function collectedRevenue(): Money
    {
        return new Money((int) $this->invoices->sum(fn (Invoice $i) => $i->paid()->cents));
    }

    /** Margin as a percentage of collected revenue. Null when nothing collected. */
    public function grossMarginPercent(): ?float
    {
        $revenue = $this->collectedRevenue();

        if ($revenue->isZero()) {
            return null;
        }

        return round($this->grossProfit()->cents / $revenue->cents * 100, 1);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeStatus(Builder $query, BookingStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof BookingStatus ? $status->value : $status);
    }

    /** Enquiries that hold their slot. */
    public function scopeBlocking(Builder $query): Builder
    {
        return $query->whereIn('status', config('booking.blocking_statuses', []));
    }

    /** Enquiries shown as tentative but not held. */
    public function scopeTentative(Builder $query): Builder
    {
        return $query->whereIn('status', config('booking.tentative_statuses', []));
    }

    /** Bookings with an event still ahead of us. */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereHas('dates', fn (Builder $q) => $q->whereDate('event_date', '>=', today()));
    }

    /*
    |--------------------------------------------------------------------------
    | Presentation
    |--------------------------------------------------------------------------
    */

    public function coupleNames(): string
    {
        return trim($this->partner_one_name.($this->partner_two_name ? ' & '.$this->partner_two_name : ''));
    }

    /** The earliest date this booking covers — what the studio sorts by. */
    public function primaryDate(): ?BookingDate
    {
        return $this->dates->sortBy('event_date')->first();
    }

    public function balance(): Money
    {
        return $this->estimated_total_cents->minus($this->deposit_cents);
    }

    /**
     * A WhatsApp deep link, pre-filled with the couple's name and reference,
     * so the studio can reply in one click rather than retyping context.
     */
    public function whatsappUrl(): ?string
    {
        $number = preg_replace('/\D+/', '', (string) $this->phone);

        if (blank($number)) {
            return null;
        }

        // Malaysian numbers are commonly entered as 01x-xxx xxxx. WhatsApp
        // needs the country code, so a leading 0 becomes 60.
        if (str_starts_with($number, '0')) {
            $number = '60'.substr($number, 1);
        }

        return 'https://wa.me/'.$number.'?text='.rawurlencode(
            __('booking.whatsapp_template', [
                'name' => $this->partner_one_name,
                'reference' => $this->reference,
            ])
        );
    }

    /** True once a pending enquiry is old enough to be auto-cancelled. */
    public function hasLapsed(): bool
    {
        return $this->status === BookingStatus::Pending
            && $this->created_at?->addDays((int) config('booking.pending_lapse_days'))->isPast();
    }
}

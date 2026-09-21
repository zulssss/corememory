<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EnquiryStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enquiry extends Model
{
    use HasFactory;

    protected $table = 'enquiries';

    protected $fillable = ['name', 'email', 'phone', 'message', 'status', 'admin_notes', 'replied_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => EnquiryStatus::class,
            'replied_at' => 'datetime',
        ];
    }

    public function scopeUnanswered(Builder $query): Builder
    {
        return $query->where('status', EnquiryStatus::New);
    }

    /** A WhatsApp reply, pre-filled — same convenience the bookings list has. */
    public function whatsappUrl(): ?string
    {
        $number = preg_replace('/\D+/', '', (string) $this->phone);

        if (blank($number)) {
            return null;
        }

        if (str_starts_with($number, '0')) {
            $number = '60'.substr($number, 1);
        }

        return 'https://wa.me/'.$number.'?text='.rawurlencode(
            __('contact.whatsapp_template', ['name' => $this->name])
        );
    }
}

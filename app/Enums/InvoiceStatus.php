<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Note there is no stored "overdue" — it is COMPUTED from the due date and how
 * much has been paid. Storing it would need a nightly job to keep it honest,
 * and would be wrong for the hours between the due date passing and the job
 * running. See Invoice::isOverdue().
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('invoice.statuses.'.$this->value);
    }

    /** Draft is the only state where line items may still be edited. */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /** Does this invoice count as receivable? A cancelled one never does. */
    public function isOutstandingCandidate(): bool
    {
        return in_array($this, [self::Sent], true);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Sent => 'warning',
            self::Paid => 'success',
            self::Cancelled => 'danger',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $s) => [$s->value => $s->label()])
            ->all();
    }
}

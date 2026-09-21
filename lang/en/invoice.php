<?php

declare(strict_types=1);

return [

    'invoice' => 'Invoice',
    'ssm' => 'SSM :number',
    'number' => 'Invoice number',
    'issued' => 'Issued',
    'due' => 'Due',
    'bill_to' => 'Bill to',
    'booking_reference' => 'Booking reference',
    'event' => 'Event',

    'types' => [
        'deposit' => 'Deposit',
        'final' => 'Final',
        'custom' => 'Custom',
    ],

    'statuses' => [
        'draft' => 'Draft',
        'sent' => 'Sent',
        'paid' => 'Paid',
        'cancelled' => 'Cancelled',
    ],

    'methods' => [
        'bank_transfer' => 'Bank transfer',
        'cash' => 'Cash',
        'ewallet' => 'E-wallet',
        'card' => 'Card',
    ],

    'cost_categories' => [
        'photographer' => 'Photographer fee',
        'videographer' => 'Videographer fee',
        'editor' => 'Editor fee',
        'travel' => 'Travel',
        'accommodation' => 'Accommodation',
        'equipment' => 'Equipment rental',
        'printing' => 'Printing / album',
        'other' => 'Miscellaneous',
    ],

    'lines' => [
        'description' => 'Description',
        'qty' => 'Qty',
        'unit_price' => 'Unit price',
        'amount' => 'Amount',
        'subtotal' => 'Subtotal',
        'deductions' => 'Deductions',
        'total' => 'Total due',
        'paid' => 'Paid',
        'outstanding' => 'Outstanding',

        'balance_after_event' => 'Less: balance payable after the event',
        'deposit_invoiced' => 'Less: deposit invoiced (:number)',
    ],

    'payment' => [
        'how_to_pay' => 'How to pay',
        'bank' => 'Bank',
        'account_name' => 'Account name',
        'account_number' => 'Account number',
        'reference_instruction' => 'Please quote :number as your transfer reference.',
        'terms' => 'Payment terms',
        'cancellation' => 'Cancellation policy',
    ],

    'overdue' => 'Overdue',
    'thank_you' => 'Thank you for choosing CoreMemory.',

    'mail' => [
        'subject' => 'Invoice :number from CoreMemory',
        'heading' => 'Invoice :number',
        'intro' => 'Hi :name, your invoice is attached. You can also download it using the button below.',
        'download' => 'Download invoice',
        'link_expiry' => 'This download link works for :days days.',
        'amount_due' => 'Amount due',
        'due_by' => 'Due by :date',
    ],

];

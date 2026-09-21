<?php

declare(strict_types=1);

return [

    'title' => 'Sales & profit',

    'basis' => [
        'label' => 'Revenue basis',
        'cash' => 'Cash — money received',
        'accrual' => 'Accrual — money invoiced',
        'help' => 'Cash counts payments actually received. Accrual counts invoices issued, paid or not.',
    ],

    'period' => [
        'label' => 'Period',
        'from' => 'From',
        'to' => 'To',
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'this_year' => 'This year',
        'last_12_months' => 'Last 12 months',
        'vs_previous' => 'vs previous period',
    ],

    'stats' => [
        'revenue' => 'Revenue',
        'collected' => 'Collected',
        'invoiced' => 'Invoiced',
        'costs' => 'Direct costs',
        'gross_profit' => 'Gross profit',
        'margin' => 'Gross margin',
        'receivables' => 'Outstanding',
        'average_booking' => 'Average booking',
        'conversion' => 'Enquiry to confirmed',
        'no_revenue' => 'No revenue in this period',
    ],

    'widgets' => [
        'trend' => 'Revenue, cost and profit',
        'trend_description' => 'Last 12 months, :basis',
        'funnel' => 'Booking funnel',
        'upcoming' => 'Upcoming events',
        'upcoming_description' => 'Next 30 days',
        'overdue' => 'Overdue invoices',
        'top_packages' => 'Revenue by package',
        'add_ons' => 'Add-on attach rate',
        'sources' => 'Where enquiries come from',
        'ageing' => 'Receivables ageing',
    ],

    'funnel' => [
        'enquiries' => 'Enquiries',
        'quoted' => 'Quoted',
        'confirmed' => 'Confirmed',
        'completed' => 'Completed',
    ],

    'ageing' => [
        'not_due' => 'Not yet due',
        '0-30' => '0–30 days',
        '31-60' => '31–60 days',
        '60+' => '60+ days',
    ],

    'empty' => 'Nothing to show for this period.',
];

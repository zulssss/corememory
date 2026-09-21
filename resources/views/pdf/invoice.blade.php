{{--
    INVOICE PDF (dompdf)

    Deliberate exception to the "no hex outside tokens.css" rule: dompdf cannot
    resolve CSS custom properties, so the PDF carries its own small palette. It
    is kept in one block at the top for the same reason tokens.css exists —
    change it here and the whole document changes. The values mirror the site's
    tokens so a printed invoice still looks like CoreMemory.

    dompdf constraints: tables for layout, no flexbox, no grid, no JS.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style>
        /* --- palette (mirrors resources/css/tokens.css) --- */
        :root { }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            line-height: 1.5;
            color: #141312;
            margin: 0;
        }
        .muted   { color: #8a857d; }
        .soft    { color: #3d3a36; }
        .accent  { color: #8c4b2f; }
        .rule    { border-top: 0.5pt solid #e2ded7; }
        .rule-strong { border-top: 1pt solid #141312; }

        .micro {
            font-size: 7pt;
            letter-spacing: 1.4pt;
            text-transform: uppercase;
            color: #8a857d;
        }

        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; padding: 0; }

        .wordmark { font-size: 16pt; font-weight: bold; letter-spacing: -0.5pt; }
        .doc-title { font-size: 22pt; font-weight: bold; letter-spacing: -1pt; }

        .items th {
            text-align: left;
            padding: 6pt 0;
            border-bottom: 0.5pt solid #e2ded7;
        }
        .items td { padding: 8pt 0; border-bottom: 0.5pt solid #e2ded7; }
        .num { text-align: right; }

        .totals td { padding: 4pt 0; }
        .grand { font-size: 13pt; font-weight: bold; }

        .badge {
            font-size: 7pt;
            letter-spacing: 1.2pt;
            text-transform: uppercase;
            padding: 3pt 6pt;
            border: 0.5pt solid #8f3a32;
            color: #8f3a32;
        }

        .pay-box { border: 0.5pt solid #e2ded7; padding: 10pt; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; }
    </style>
</head>
<body>

    {{-- ---------------- Letterhead ---------------- --}}
    <table>
        <tr>
            <td style="width: 55%;">
                <div class="wordmark">{{ __('site.brand.name') }}</div>
                <div class="muted" style="font-size: 8.5pt; margin-top: 4pt;">
                    {{ $studio['name'] }}<br>
                    @if ($studio['ssm'])
                        {{ __('invoice.ssm', ['number' => $studio['ssm']]) }}<br>
                    @endif
                    @if ($studio['address'])
                        {!! nl2br(e($studio['address'])) !!}<br>
                    @endif
                    @if ($studio['email']){{ $studio['email'] }}@endif
                    @if ($studio['phone']) · {{ $studio['phone'] }}@endif
                </div>
            </td>

            <td style="width: 45%; text-align: right;">
                <div class="doc-title">{{ __('invoice.invoice') }}</div>
                <div class="micro" style="margin-top: 2pt;">{{ $invoice->type->label() }}</div>

                <table style="margin-top: 10pt;">
                    <tr>
                        <td class="micro" style="text-align: right;">{{ __('invoice.number') }}</td>
                        <td style="text-align: right; padding-left: 10pt; width: 45%;">
                            <strong>{{ $invoice->number }}</strong>
                        </td>
                    </tr>
                    <tr>
                        <td class="micro" style="text-align: right;">{{ __('invoice.issued') }}</td>
                        <td style="text-align: right; padding-left: 10pt;">
                            {{ $invoice->issued_at?->translatedFormat('d M Y') ?? '—' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="micro" style="text-align: right;">{{ __('invoice.due') }}</td>
                        <td style="text-align: right; padding-left: 10pt;">
                            {{ $invoice->due_at?->translatedFormat('d M Y') ?? '—' }}
                        </td>
                    </tr>
                </table>

                @if ($invoice->isOverdue())
                    <div style="margin-top: 8pt;">
                        <span class="badge">{{ __('invoice.overdue') }}</span>
                    </div>
                @endif
            </td>
        </tr>
    </table>

    {{-- ---------------- Bill to ---------------- --}}
    <table style="margin-top: 26pt;">
        <tr>
            <td style="width: 50%;">
                <div class="micro">{{ __('invoice.bill_to') }}</div>
                <div style="margin-top: 4pt;">
                    <strong>{{ $invoice->client_name }}</strong><br>
                    <span class="muted" style="font-size: 9pt;">
                        @if ($invoice->client_email){{ $invoice->client_email }}<br>@endif
                        @if ($invoice->client_phone){{ $invoice->client_phone }}<br>@endif
                        @if ($invoice->client_address){!! nl2br(e($invoice->client_address)) !!}@endif
                    </span>
                </div>
            </td>

            <td style="width: 50%;">
                @if ($invoice->booking)
                    <div class="micro">{{ __('invoice.booking_reference') }}</div>
                    <div style="margin-top: 4pt;"><strong>{{ $invoice->booking->reference }}</strong></div>

                    @if ($invoice->booking->dates->isNotEmpty())
                        <div class="micro" style="margin-top: 10pt;">{{ __('invoice.event') }}</div>
                        <div class="muted" style="font-size: 9pt; margin-top: 3pt;">
                            @foreach ($invoice->booking->dates as $date)
                                {{ $date->event_date->translatedFormat('d M Y') }}
                                — {{ $date->session_slot->label() }}
                                @if ($date->venue)<br><span style="padding-left: 0;">{{ $date->venue }}</span>@endif
                                <br>
                            @endforeach
                        </div>
                    @endif
                @endif
            </td>
        </tr>
    </table>

    {{-- ---------------- Line items ---------------- --}}
    <table class="items" style="margin-top: 26pt;">
        <thead>
            <tr>
                <th class="micro" style="width: 58%;">{{ __('invoice.lines.description') }}</th>
                <th class="micro num" style="width: 8%;">{{ __('invoice.lines.qty') }}</th>
                <th class="micro num" style="width: 17%;">{{ __('invoice.lines.unit_price') }}</th>
                <th class="micro num" style="width: 17%;">{{ __('invoice.lines.amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td class="{{ $item->total_cents->isNegative() ? 'muted' : '' }}">{{ $item->description }}</td>
                    <td class="num muted">{{ $item->qty > 1 ? $item->qty : '' }}</td>
                    <td class="num muted">
                        {{ $item->qty > 1 ? $item->unit_price_cents->format() : '' }}
                    </td>
                    <td class="num {{ $item->total_cents->isNegative() ? 'muted' : '' }}">
                        {{ $item->total_cents->format() }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ---------------- Totals ---------------- --}}
    <table style="margin-top: 14pt;">
        <tr>
            <td style="width: 55%;"></td>
            <td style="width: 45%;">
                <table class="totals">
                    <tr>
                        <td class="micro">{{ __('invoice.lines.subtotal') }}</td>
                        <td class="num">{{ $invoice->subtotal_cents->format() }}</td>
                    </tr>

                    @if (! $invoice->discount_cents->isZero())
                        <tr>
                            <td class="micro">{{ __('invoice.lines.deductions') }}</td>
                            <td class="num muted">−{{ $invoice->discount_cents->format() }}</td>
                        </tr>
                    @endif

                    <tr>
                        <td colspan="2" class="rule-strong" style="padding: 0; height: 6pt;"></td>
                    </tr>
                    <tr>
                        <td class="grand">{{ __('invoice.lines.total') }}</td>
                        <td class="num grand">{{ $invoice->total_cents->format() }}</td>
                    </tr>

                    @if (! $invoice->paid()->isZero())
                        <tr>
                            <td class="micro" style="padding-top: 8pt;">{{ __('invoice.lines.paid') }}</td>
                            <td class="num" style="padding-top: 8pt;">−{{ $invoice->paid()->format() }}</td>
                        </tr>
                        <tr>
                            <td class="micro"><strong>{{ __('invoice.lines.outstanding') }}</strong></td>
                            <td class="num"><strong>{{ $invoice->outstanding()->format() }}</strong></td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    {{-- ---------------- How to pay ---------------- --}}
    @if ($studio['bank_account_number'])
        <div class="pay-box" style="margin-top: 26pt;">
            <div class="micro">{{ __('invoice.payment.how_to_pay') }}</div>

            <table style="margin-top: 8pt;">
                <tr>
                    <td style="width: 50%;">
                        <span class="micro">{{ __('invoice.payment.bank') }}</span><br>
                        {{ $studio['bank_name'] }}
                    </td>
                    <td style="width: 50%;">
                        <span class="micro">{{ __('invoice.payment.account_name') }}</span><br>
                        {{ $studio['bank_account_name'] }}
                    </td>
                </tr>
                <tr>
                    <td style="padding-top: 8pt;">
                        <span class="micro">{{ __('invoice.payment.account_number') }}</span><br>
                        <strong>{{ $studio['bank_account_number'] }}</strong>
                    </td>
                    <td style="padding-top: 8pt;"></td>
                </tr>
            </table>

            <div class="soft" style="margin-top: 10pt; font-size: 9pt;">
                {{ __('invoice.payment.reference_instruction', ['number' => $invoice->number]) }}
            </div>
        </div>
    @endif

    {{-- ---------------- Terms ---------------- --}}
    @if ($studio['payment_terms'] || $studio['cancellation_policy'])
        <div class="rule" style="margin-top: 22pt; padding-top: 10pt;">
            @if ($studio['payment_terms'])
                <div class="micro">{{ __('invoice.payment.terms') }}</div>
                <div class="muted" style="font-size: 8.5pt; margin-top: 3pt;">{{ $studio['payment_terms'] }}</div>
            @endif

            @if ($studio['cancellation_policy'])
                <div class="micro" style="margin-top: 8pt;">{{ __('invoice.payment.cancellation') }}</div>
                <div class="muted" style="font-size: 8.5pt; margin-top: 3pt;">{{ $studio['cancellation_policy'] }}</div>
            @endif
        </div>
    @endif

    @if ($invoice->notes)
        <div class="soft" style="margin-top: 14pt; font-size: 9pt;">{{ $invoice->notes }}</div>
    @endif

    <div class="muted" style="margin-top: 22pt; font-size: 8.5pt;">
        {{ __('invoice.thank_you') }}
    </div>

</body>
</html>

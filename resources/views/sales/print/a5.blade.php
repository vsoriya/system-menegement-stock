{{--
    A5 paper invoice.

    Standalone on purpose: no Tailwind, no app layout, no theme tokens. A printer
    has no dark mode and no sidebar, and the compiled stylesheet would drag both
    into the page. Everything here is plain CSS sized in millimetres.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $sale->number }}</title>
    <style>
        @page {
            size: A5 portrait;
            margin: 12mm;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            /* Khmer first, so Khmer text is not rendered by a fallback that
               drops the diacritics. */
            font-family: 'Khmer OS Battambang', 'Khmer OS', 'Noto Sans Khmer', Hanuman,
                         system-ui, -apple-system, 'Segoe UI', sans-serif;
            font-size: 11pt;
            line-height: 1.45;
            color: #111;
            background: #fff;
        }

        .sheet { max-width: 122mm; margin: 0 auto; }

        .toolbar {
            margin-bottom: 6mm;
            display: flex;
            gap: 6px;
        }

        .toolbar a, .toolbar button {
            font: inherit;
            font-size: 10pt;
            padding: 4px 10px;
            border: 1px solid #999;
            border-radius: 6px;
            background: #f4f4f5;
            color: #111;
            text-decoration: none;
            cursor: pointer;
        }

        header { border-bottom: 1.5pt solid #111; padding-bottom: 4mm; }

        .shop-name { font-size: 15pt; font-weight: 700; margin: 0; }
        .shop-line { font-size: 9.5pt; color: #444; margin: 0.5mm 0 0; }

        .title-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 8mm;
            margin-top: 4mm;
        }

        .doc-title { font-size: 13pt; font-weight: 700; margin: 0; letter-spacing: 0.5pt; }
        .doc-number { font-size: 12pt; font-weight: 700; margin: 0; }

        .meta { width: 100%; margin: 4mm 0; font-size: 10pt; border-collapse: collapse; }
        .meta td { padding: 0.8mm 0; vertical-align: top; }
        .meta .label { color: #555; width: 26mm; }
        .meta .pair-gap { width: 6mm; }

        table.lines { width: 100%; border-collapse: collapse; margin-top: 2mm; font-size: 10pt; }

        table.lines thead th {
            border-top: 0.8pt solid #111;
            border-bottom: 0.8pt solid #111;
            padding: 1.6mm 1mm;
            text-align: left;
            font-weight: 700;
        }

        table.lines tbody td {
            border-bottom: 0.4pt solid #ccc;
            padding: 1.6mm 1mm;
            vertical-align: top;
        }

        .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .sku { font-size: 8.5pt; color: #666; }

        .totals { width: 62mm; margin-left: auto; margin-top: 3mm; border-collapse: collapse; font-size: 10.5pt; }
        .totals td { padding: 1mm 1mm; }
        .totals .label { color: #444; }
        .totals .grand td {
            border-top: 0.8pt solid #111;
            border-bottom: 1.5pt solid #111;
            font-size: 12pt;
            font-weight: 700;
        }

        footer { margin-top: 8mm; text-align: center; font-size: 9.5pt; color: #444; }
        .sign-row { display: flex; justify-content: space-between; gap: 10mm; margin-top: 12mm; font-size: 9.5pt; }
        .sign-box { flex: 1; border-top: 0.5pt solid #666; padding-top: 1.5mm; text-align: center; color: #555; }

        .void-stamp {
            margin: 3mm 0;
            padding: 2mm 3mm;
            border: 1.5pt solid #b91c1c;
            color: #b91c1c;
            font-weight: 700;
            text-align: center;
            letter-spacing: 1pt;
        }

        @media print {
            .toolbar { display: none; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="toolbar">
            <button type="button" onclick="window.print()">{{ __('app.common.print') }}</button>
            <a href="{{ route('sales.show', $sale) }}">{{ __('app.common.back') }}</a>
            <a href="{{ route('sales.print', [$sale, 'receipt']) }}">{{ __('app.sale.print_receipt') }}</a>
        </div>

        <header>
            <p class="shop-name">{{ config('app.name') }}</p>

            @if (filled(config('app.shop.address')))
                <p class="shop-line">{{ config('app.shop.address') }}</p>
            @endif

            @if (filled(config('app.shop.phone')))
                <p class="shop-line">{{ __('app.customer.phone') }}: {{ config('app.shop.phone') }}</p>
            @endif

            @if (filled(config('app.shop.tax_number')))
                <p class="shop-line">{{ __('app.sale.tax_number') }}: {{ config('app.shop.tax_number') }}</p>
            @endif

            <div class="title-row">
                <p class="doc-title">{{ __('app.sale.invoice') }}</p>
                <p class="doc-number">{{ $sale->number }}</p>
            </div>
        </header>

        @if ($sale->is_voided)
            <p class="void-stamp">
                {{ __('app.sale.voided_banner') }}
                @if ($sale->voided_at)
                    &mdash; {{ $sale->voided_at->format('d/m/Y H:i') }}
                @endif
            </p>
        @endif

        <table class="meta">
            <tr>
                <td class="label">{{ __('app.sale.sold_at') }}</td>
                <td>{{ $sale->sold_at->format('d/m/Y H:i') }}</td>
                <td class="pair-gap"></td>
                <td class="label">{{ __('app.customer.one') }}</td>
                <td>{{ $sale->customer?->name ?? __('app.customer.walk_in') }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('app.sale.cashier') }}</td>
                <td>{{ $sale->cashier?->name ?? '—' }}</td>
                <td class="pair-gap"></td>
                <td class="label">{{ __('app.customer.phone') }}</td>
                <td>{{ $sale->customer?->phone ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('app.sale.payment_method') }}</td>
                <td>{{ $sale->payment_method->label() }}</td>
                <td class="pair-gap"></td>
                <td class="label">{{ __('app.sale.item_count') }}</td>
                <td>{{ number_format($sale->item_count) }}</td>
            </tr>
        </table>

        <table class="lines">
            <thead>
                <tr>
                    <th style="width: 8mm;">#</th>
                    <th>{{ __('app.product.one') }}</th>
                    <th class="num" style="width: 16mm;">{{ __('app.movement.quantity') }}</th>
                    <th class="num" style="width: 22mm;">{{ __('app.sale.unit_price') }}</th>
                    <th class="num" style="width: 24mm;">{{ __('app.sale.line_total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sale->lines as $index => $line)
                    <tr>
                        <td class="num">{{ $index + 1 }}</td>
                        <td>
                            {{ $line->product?->name ?? '—' }}
                            @if (filled($line->product?->sku))
                                <div class="sku">{{ $line->product->sku }}</div>
                            @endif
                        </td>
                        <td class="num">{{ number_format($line->quantity) }} {{ $line->product?->unit }}</td>
                        <td class="num">@money($line->unit_price)</td>
                        <td class="num">@money($line->line_total)</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals">
            <tr>
                <td class="label">{{ __('app.sale.subtotal') }}</td>
                <td class="num">@money($sale->subtotal)</td>
            </tr>

            @if ((float) $sale->discount > 0)
                <tr>
                    <td class="label">{{ __('app.sale.discount') }}</td>
                    <td class="num">-@money($sale->discount)</td>
                </tr>
            @endif

            <tr class="grand">
                <td>{{ __('app.sale.total') }}</td>
                <td class="num">@money($sale->total)</td>
            </tr>
            <tr>
                <td class="label">{{ __('app.sale.paid') }}</td>
                <td class="num">@money($sale->paid)</td>
            </tr>

            @if ((float) $sale->change_due > 0)
                <tr>
                    <td class="label">{{ __('app.sale.change_due') }}</td>
                    <td class="num">@money($sale->change_due)</td>
                </tr>
            @endif
        </table>

        @if (filled($sale->note) && ! $sale->is_voided)
            <p style="margin-top: 4mm; font-size: 9.5pt;">
                <strong>{{ __('app.sale.note') }}:</strong> {{ $sale->note }}
            </p>
        @endif

        <div class="sign-row">
            <div class="sign-box">{{ __('app.sale.signature_seller') }}</div>
            <div class="sign-box">{{ __('app.sale.signature_buyer') }}</div>
        </div>

        <footer>
            <p style="margin: 0;">{{ config('app.shop.footer') ?: __('app.sale.thank_you') }}</p>
        </footer>
    </div>

    <script>
        // Opens the print dialog on arrival, so the counter is one keystroke
        // from paper. Cancelling it leaves the page on screen.
        window.addEventListener('load', () => window.print());
    </script>
</body>
</html>

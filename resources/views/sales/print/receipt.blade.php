{{--
    80mm thermal receipt.

    An 80mm roll has roughly 72mm of printable width, so everything is sized to
    that and the page height is left to grow with the basket. Kept to plain CSS
    and no colour: thermal heads print in one shade, and any grey background
    just comes out as a smear.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $sale->number }}</title>
    <style>
        @page {
            /* auto height, so the roll is cut to the length of the sale. */
            size: 80mm auto;
            margin: 0;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 3mm;
            width: 80mm;
            font-family: 'Khmer OS Battambang', 'Khmer OS', 'Noto Sans Khmer', Hanuman,
                         system-ui, -apple-system, sans-serif;
            font-size: 9pt;
            line-height: 1.35;
            color: #000;
            background: #fff;
        }

        .toolbar { margin-bottom: 3mm; display: flex; gap: 4px; }

        .toolbar a, .toolbar button {
            font: inherit;
            font-size: 8pt;
            padding: 3px 7px;
            border: 1px solid #999;
            border-radius: 5px;
            background: #f4f4f5;
            color: #000;
            text-decoration: none;
            cursor: pointer;
        }

        .center { text-align: center; }
        .bold { font-weight: 700; }
        .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }

        .shop-name { font-size: 12pt; font-weight: 700; margin: 0; }
        .shop-line { margin: 0.3mm 0 0; font-size: 8pt; }

        /* Dashed rules stand in for the ruled lines a receipt normally has. */
        .rule { border-top: 1px dashed #000; margin: 2mm 0; }

        table { width: 100%; border-collapse: collapse; }
        td { padding: 0.4mm 0; vertical-align: top; }

        .meta td:first-child { color: #000; width: 22mm; }

        /* Name on its own row above the figures, because 72mm is not wide
           enough to keep a product name and three numbers on one line. */
        .line-name { padding-top: 1.2mm; }
        .line-sku { font-size: 7.5pt; }

        .total-row td { font-size: 11pt; font-weight: 700; padding-top: 1mm; }

        .void-stamp {
            margin: 2mm 0;
            padding: 1.5mm;
            border: 1.5px solid #000;
            text-align: center;
            font-weight: 700;
            letter-spacing: 1px;
        }

        footer { margin-top: 3mm; text-align: center; font-size: 8pt; }

        @media print {
            .toolbar { display: none; }
            body { padding: 0 2mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">{{ __('app.common.print') }}</button>
        <a href="{{ route('sales.show', $sale) }}">{{ __('app.common.back') }}</a>
        <a href="{{ route('sales.print', [$sale, 'a5']) }}">{{ __('app.sale.print_invoice') }}</a>
    </div>

    <div class="center">
        <p class="shop-name">{{ config('app.name') }}</p>

        @if (filled(config('app.shop.address')))
            <p class="shop-line">{{ config('app.shop.address') }}</p>
        @endif

        @if (filled(config('app.shop.phone')))
            <p class="shop-line">{{ config('app.shop.phone') }}</p>
        @endif

        @if (filled(config('app.shop.tax_number')))
            <p class="shop-line">{{ __('app.sale.tax_number') }}: {{ config('app.shop.tax_number') }}</p>
        @endif
    </div>

    <div class="rule"></div>

    @if ($sale->is_voided)
        <p class="void-stamp">{{ __('app.sale.voided_banner') }}</p>
    @endif

    <table class="meta">
        <tr>
            <td>{{ __('app.sale.number') }}</td>
            <td class="bold">{{ $sale->number }}</td>
        </tr>
        <tr>
            <td>{{ __('app.sale.sold_at') }}</td>
            <td>{{ $sale->sold_at->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td>{{ __('app.sale.cashier') }}</td>
            <td>{{ $sale->cashier?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td>{{ __('app.customer.one') }}</td>
            <td>{{ $sale->customer?->name ?? __('app.customer.walk_in') }}</td>
        </tr>
    </table>

    <div class="rule"></div>

    <table>
        @foreach ($sale->lines as $line)
            <tr>
                <td class="line-name" colspan="3">
                    {{ $line->product?->name ?? '—' }}
                    @if (filled($line->product?->sku))
                        <div class="line-sku">{{ $line->product->sku }}</div>
                    @endif
                </td>
            </tr>
            <tr>
                <td style="width: 22mm;">
                    {{ number_format($line->quantity) }} {{ $line->product?->unit }}
                </td>
                <td class="num">&times; @money($line->unit_price)</td>
                <td class="num bold" style="width: 24mm;">@money($line->line_total)</td>
            </tr>
        @endforeach
    </table>

    <div class="rule"></div>

    <table>
        <tr>
            <td>{{ __('app.sale.subtotal') }}</td>
            <td class="num">@money($sale->subtotal)</td>
        </tr>

        @if ((float) $sale->discount > 0)
            <tr>
                <td>{{ __('app.sale.discount') }}</td>
                <td class="num">-@money($sale->discount)</td>
            </tr>
        @endif

        <tr class="total-row">
            <td>{{ __('app.sale.total') }}</td>
            <td class="num">@money($sale->total)</td>
        </tr>
        <tr>
            <td>{{ $sale->payment_method->label() }}</td>
            <td class="num">@money($sale->paid)</td>
        </tr>

        @if ((float) $sale->change_due > 0)
            <tr>
                <td>{{ __('app.sale.change_due') }}</td>
                <td class="num bold">@money($sale->change_due)</td>
            </tr>
        @endif

        <tr>
            <td>{{ __('app.sale.item_count') }}</td>
            <td class="num">{{ number_format($sale->item_count) }}</td>
        </tr>
    </table>

    <div class="rule"></div>

    <footer>
        <p style="margin: 0;">{{ config('app.shop.footer') ?: __('app.sale.thank_you') }}</p>
    </footer>

    <script>
        window.addEventListener('load', () => window.print());
    </script>
</body>
</html>

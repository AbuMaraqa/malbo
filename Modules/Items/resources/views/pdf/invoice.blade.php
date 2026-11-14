@php
    /** @var array<string, mixed> $doc  Modules\Items\Support\OrderDocument::for() */
    $start = $doc['dir'] === 'rtl' ? 'right' : 'left';
    $end = $doc['dir'] === 'rtl' ? 'left' : 'right';
@endphp
<html lang="{{ $doc['locale'] }}" dir="{{ $doc['dir'] }}">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: dejavusans; font-size: 9.5pt; color: #1f2937; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; text-align: {{ $start }}; }
        .muted { color: #6b7280; }
        .ltr { direction: ltr; }
        .brand { font-size: 16pt; font-weight: bold; }
        .title { font-size: 20pt; font-weight: bold; text-align: {{ $end }}; }
        .meta td { padding: 1mm 0; }
        .box { border: 0.3mm solid #d1d5db; padding: 3mm; }
        .label { font-size: 8pt; color: #6b7280; text-transform: uppercase; }
        .lines th { background-color: #f3f4f6; border-bottom: 0.3mm solid #d1d5db; padding: 2mm; font-size: 8.5pt; }
        .lines td { border-bottom: 0.2mm solid #e5e7eb; padding: 2mm; }
        .num { text-align: {{ $end }}; white-space: nowrap; }
        .totals td { padding: 1.2mm 2mm; }
        .grand td { font-size: 11.5pt; font-weight: bold; border-top: 0.4mm solid #1f2937; }
        .footer { margin-top: 8mm; text-align: center; font-size: 8.5pt; color: #6b7280; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td style="width: 55%;">
                @if ($doc['store']['logo'])
                    <img src="{{ $doc['store']['logo'] }}" style="height: 16mm;" alt="">
                    <br>
                @endif
                <span class="brand">{{ $doc['store']['name'] }}</span>
                @if ($doc['store']['phone'] || $doc['store']['email'])
                    <br><span class="muted"><bdo dir="ltr">{{ collect([$doc['store']['phone'], $doc['store']['email']])->filter()->implode(' · ') }}</bdo></span>
                @endif
            </td>
            <td style="width: 45%;">
                <div class="title">{{ __('Invoice') }}</div>
                <table class="meta">
                    <tr>
                        <td class="muted">{{ __('Order Number') }}</td>
                        <td class="num ltr">{{ $doc['number'] }}</td>
                    </tr>
                    <tr>
                        <td class="muted">{{ __('Date') }}</td>
                        <td class="num ltr">{{ $doc['date'] }}</td>
                    </tr>
                    <tr>
                        <td class="muted">{{ __('Order Status') }}</td>
                        <td class="num">{{ $doc['status'] }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table style="margin-top: 6mm;">
        <tr>
            <td class="box" style="width: 60%;">
                <div class="label">{{ __('Bill to') }}</div>
                <strong>{{ $doc['customer']['name'] }}</strong><br>
                @if ($doc['customer']['phone'])<bdo dir="ltr">{{ $doc['customer']['phone'] }}</bdo><br>@endif
                @if ($doc['customer']['email'])<bdo dir="ltr">{{ $doc['customer']['email'] }}</bdo><br>@endif
                {{ $doc['customer']['address'] }}<br>
                {{ collect([$doc['customer']['district'], $doc['customer']['city']])->filter()->implode('، ') }}
            </td>
            <td style="width: 3%;"></td>
            <td class="box" style="width: 37%;">
                <div class="label">{{ __('Payment details') }}</div>
                {{ __('Payment Method') }}: {{ $doc['payment_method'] }}<br>
                {{ __('Payment Status') }}: {{ $doc['payment_status'] }}
                @if ($doc['tracking_number'])
                    <br>{{ $doc['courier'] }}: <bdo dir="ltr">{{ $doc['tracking_number'] }}</bdo>
                @endif
            </td>
        </tr>
    </table>

    <table class="lines" style="margin-top: 6mm;">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 43%;">{{ __('Product') }}</th>
                <th style="width: 16%;">{{ __('SKU') }}</th>
                <th class="num" style="width: 8%;">{{ __('Quantity') }}</th>
                <th class="num" style="width: 14%;">{{ __('Unit price') }}</th>
                <th class="num" style="width: 14%;">{{ __('Line total') }}</th>
            </tr>
        </thead>
        <tbody>
            @php $number = 0; @endphp
            @foreach ($doc['lines'] as $line)
                @php $set = $line['bundle'] ?? null; @endphp
                @if ($set && $set['first'])
                    {{-- A set or bundle: its own row with its price, then its pieces. --}}
                    <tr>
                        <td>{{ ++$number }}</td>
                        <td><strong>{{ $set['name'] }}</strong></td>
                        <td class="ltr">{{ $set['sku'] }}</td>
                        <td class="num">{{ $set['quantity'] }}</td>
                        <td class="num">{{ $set['unit_price'] }}</td>
                        <td class="num">{{ $set['total'] }}</td>
                    </tr>
                @endif
                <tr>
                    <td>{{ $set ? '' : ++$number }}</td>
                    <td @if ($set) style="padding-{{ $start }}: 6mm;" @endif>
                        {{ $line['name'] }}
                        @if ($line['options'] !== '')
                            <br><span class="muted">{{ $line['options'] }}</span>
                        @endif
                    </td>
                    <td class="ltr">{{ $line['sku'] }}</td>
                    <td class="num">{{ $line['quantity'] }}</td>
                    <td class="num">{{ $set ? '' : $line['unit_price'] }}</td>
                    <td class="num">{{ $set ? '' : $line['total'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="margin-top: 4mm;">
        <tr>
            <td style="width: 55%;">
                @if ($doc['notes'])
                    <div class="label">{{ __('Notes') }}</div>
                    {{ $doc['notes'] }}
                @endif
            </td>
            <td style="width: 45%;">
                <table class="totals">
                    <tr>
                        <td>{{ __('Sub Total') }}</td>
                        <td class="num">{{ $doc['sub_total'] }}</td>
                    </tr>
                    @if ($doc['discount'])
                        <tr>
                            <td>
                                {{ __('Discount') }}
                                @if ($doc['coupon_code'])<span class="muted">(<bdo dir="ltr">{{ $doc['coupon_code'] }}</bdo>)</span>@endif
                            </td>
                            <td class="num">−{{ $doc['discount'] }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td>{{ __('Delivery Fee') }}</td>
                        <td class="num">{{ $doc['delivery_fee'] }}</td>
                    </tr>
                    <tr class="grand">
                        <td>{{ __('Grand Total') }}</td>
                        <td class="num">{{ $doc['total'] }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="footer">{{ __('Thank you for shopping with :store.', ['store' => $doc['store']['name']]) }}</div>
</body>
</html>

@php
    /** @var array<string, mixed> $doc  Modules\Wholesale\Services\MerchantStatement::pdf() */
    $start = $doc['dir'] === 'rtl' ? 'right' : 'left';
    $end = $doc['dir'] === 'rtl' ? 'left' : 'right';
@endphp
<html lang="{{ $doc['locale'] }}" dir="{{ $doc['dir'] }}">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: dejavusans; font-size: 9pt; color: #1f2937; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; text-align: {{ $start }}; }
        .muted { color: #6b7280; }
        .brand { font-size: 15pt; font-weight: bold; }
        .title { font-size: 18pt; font-weight: bold; text-align: {{ $end }}; }
        .meta { text-align: {{ $end }}; }
        .lines th { background-color: #f3f4f6; border-bottom: 0.3mm solid #d1d5db; padding: 2mm; font-size: 8.5pt; }
        .lines td { border-bottom: 0.2mm solid #e5e7eb; padding: 2mm; }
        .num { text-align: {{ $end }}; white-space: nowrap; }
        .strong td { font-weight: bold; background-color: #f9fafb; }
        .footer { margin-top: 8mm; text-align: center; font-size: 8pt; color: #6b7280; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td style="width: 50%;">
                <span class="brand">{{ $doc['store'] }}</span><br>
                <strong>{{ $doc['shop'] }}</strong><br>
                <span class="muted">{{ $doc['owner'] }} · <bdo dir="ltr">{{ $doc['phone'] }}</bdo></span>
            </td>
            <td style="width: 50%;">
                <div class="title">{{ __('Statement of account') }}</div>
                <div class="meta muted">
                    {{ __('From') }} <bdo dir="ltr">{{ $doc['from'] ?? '—' }}</bdo>
                    {{ __('to') }} <bdo dir="ltr">{{ $doc['to'] ?? '—' }}</bdo>
                </div>
                @if ($doc['credit'])
                    <div class="meta muted">{{ __('Credit limit') }}: {{ $doc['credit']['limit_label'] }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="lines" style="margin-top: 6mm;">
        <thead>
            <tr>
                <th style="width: 13%;">{{ __('Date') }}</th>
                <th style="width: 15%;">{{ __('Type') }}</th>
                <th>{{ __('Description') }}</th>
                <th class="num" style="width: 13%;">{{ __('Debit') }}</th>
                <th class="num" style="width: 13%;">{{ __('Credit') }}</th>
                <th class="num" style="width: 14%;">{{ __('Balance') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr class="strong">
                <td colspan="5">{{ __('Balance brought forward') }}</td>
                <td class="num">{{ $doc['opening_label'] }}</td>
            </tr>
            @foreach ($doc['rows'] as $row)
                <tr>
                    <td><bdo dir="ltr">{{ $row['date'] }}</bdo></td>
                    <td>{{ $row['type_label'] }}</td>
                    <td>
                        {{ $row['description'] }}
                        @if (! empty($row['note']))
                            <br><span class="muted">{{ $row['note'] }}</span>
                        @endif
                    </td>
                    <td class="num">{{ $row['debit_label'] }}</td>
                    <td class="num">{{ $row['credit_label'] }}</td>
                    <td class="num">{{ $row['balance_label'] }}</td>
                </tr>
            @endforeach
            <tr class="strong">
                <td colspan="3">{{ __('Totals and closing balance') }}</td>
                <td class="num">{{ $doc['debits_label'] }}</td>
                <td class="num">{{ $doc['credits_label'] }}</td>
                <td class="num">{{ $doc['closing_label'] }}</td>
            </tr>
        </tbody>
    </table>

    <p class="footer">{{ __('A positive balance is what the shop owes.') }} · <bdo dir="ltr">{{ $doc['printed_at'] }}</bdo></p>
</body>
</html>

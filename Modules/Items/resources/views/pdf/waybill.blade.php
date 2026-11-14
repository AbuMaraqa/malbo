@php
    /** @var list<array<string, mixed>> $docs  Modules\Items\Support\OrderDocument::for(), one a page */
    $dir = $docs[0]['dir'] ?? 'rtl';
    $start = $dir === 'rtl' ? 'right' : 'left';
    $end = $dir === 'rtl' ? 'left' : 'right';
@endphp
<html lang="{{ $docs[0]['locale'] ?? 'ar' }}" dir="{{ $dir }}">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: dejavusans; font-size: 9pt; color: #111827; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; text-align: {{ $start }}; }
        .ltr { direction: ltr; text-align: {{ $start }}; }
        .store { font-size: 11pt; font-weight: bold; }
        .number { font-size: 14pt; font-weight: bold; text-align: {{ $end }}; }
        .code { text-align: center; padding: 2mm 0 1mm; }
        .section { border-top: 0.4mm solid #111827; padding-top: 2mm; margin-top: 2mm; }
        .label { font-size: 7.5pt; color: #4b5563; }
        .name { font-size: 12pt; font-weight: bold; }
        .phone { font-size: 11pt; font-weight: bold; }
        .collect { border: 0.6mm solid #111827; padding: 2mm; text-align: center; font-size: 13pt; font-weight: bold; margin-top: 2mm; }
        .small { font-size: 8pt; }
    </style>
</head>
<body>
@foreach ($docs as $doc)
    <table>
        <tr>
            <td class="store" style="width: 50%;">{{ $doc['store']['name'] }}</td>
            <td class="number ltr" style="width: 50%;">#{{ $doc['number'] }}</td>
        </tr>
    </table>

    <div class="code">
        <barcode code="{{ $doc['number'] }}" type="C128B" size="0.9" height="1.4" />
        <div class="small" style="direction: ltr; text-align: center;">{{ $doc['number'] }}</div>
    </div>

    <div class="section">
        <div class="label">{{ __('Ship to') }}</div>
        <div class="name">{{ $doc['customer']['name'] }}</div>
        @if ($doc['customer']['phone'])
            <div class="phone ltr">{{ $doc['customer']['phone'] }}</div>
        @endif
        <div>{{ $doc['customer']['address'] }}</div>
        <div><strong>{{ collect([$doc['customer']['district'], $doc['customer']['city']])->filter()->implode('، ') }}</strong></div>
    </div>

    <div class="section">
        <table>
            <tr>
                <td style="width: 50%;">
                    <div class="label">{{ __('Items') }}</div>
                    <strong>{{ $doc['item_count'] }}</strong>
                </td>
                <td style="width: 50%;">
                    <div class="label">{{ __('Date') }}</div>
                    <bdo dir="ltr">{{ $doc['date'] }}</bdo>
                </td>
            </tr>
            @if ($doc['courier'])
                <tr>
                    <td style="padding-top: 2mm;">
                        <div class="label">{{ __('Courier') }}</div>
                        {{ $doc['courier'] }}
                    </td>
                    <td style="padding-top: 2mm;">
                        <div class="label">{{ __('Tracking number') }}</div>
                        <bdo dir="ltr">{{ $doc['tracking_number'] ?? '—' }}</bdo>
                    </td>
                </tr>
            @endif
        </table>
    </div>

    @if ($doc['collect'])
        <div class="collect">{{ __('Collect on delivery') }}: {{ $doc['collect'] }}</div>
    @else
        <div class="collect">{{ __('Nothing to collect') }}</div>
    @endif

    @if ($doc['notes'])
        <div class="section small">
            <div class="label">{{ __('Notes') }}</div>
            {{ \Illuminate\Support\Str::limit($doc['notes'], 200) }}
        </div>
    @endif

    <div class="section small">{{ __('From') }}: {{ $doc['store']['name'] }}@if ($doc['store']['phone']) · <bdo dir="ltr">{{ $doc['store']['phone'] }}</bdo>@endif</div>

    @unless ($loop->last)
        <pagebreak />
    @endunless
@endforeach
</body>
</html>

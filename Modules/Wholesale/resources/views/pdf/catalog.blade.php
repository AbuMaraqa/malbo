@php
    /** @var array<string, mixed> $doc  Modules\Wholesale\Services\CatalogPdfService::document() */
    $start = $doc['dir'] === 'rtl' ? 'right' : 'left';
    $end = $doc['dir'] === 'rtl' ? 'left' : 'right';
    $rows = array_chunk($doc['products'], 2);
@endphp
<html lang="{{ $doc['locale'] }}" dir="{{ $doc['dir'] }}">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: dejavusans; font-size: 9pt; color: #1f2937; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; text-align: {{ $start }}; }
        .muted { color: #6b7280; }
        .brand { font-size: 15pt; font-weight: bold; }
        .title { font-size: 18pt; font-weight: bold; text-align: {{ $end }}; }
        .meta { text-align: {{ $end }}; }
        .card { border: 0.3mm solid #e5e7eb; padding: 3mm; }
        .image { text-align: center; height: 48mm; }
        .name { font-size: 11pt; font-weight: bold; margin-top: 2mm; }
        .price { font-size: 12pt; font-weight: bold; color: #111827; }
        .retail { color: #6b7280; }
        .breaks { font-size: 8pt; color: #374151; }
        .note { margin-top: 6mm; font-size: 8pt; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td style="width: 55%;">
                @if ($doc['store']['logo'])
                    <img src="{{ $doc['store']['logo'] }}" style="height: 14mm;" alt="">
                    <br>
                @endif
                <span class="brand">{{ $doc['store']['name'] }}</span>
            </td>
            <td style="width: 45%;">
                <div class="title">{{ $doc['prices'] === 'wholesale' ? __('Wholesale catalog') : __('Catalog') }}</div>
                <div class="meta">{{ $doc['shop'] }}</div>
                @if ($doc['category'])
                    <div class="meta muted">{{ $doc['category'] }}</div>
                @endif
                <div class="meta muted"><bdo dir="ltr">{{ $doc['date'] }}</bdo></div>
            </td>
        </tr>
    </table>

    @if ($doc['products'] === [])
        <p class="note">{{ __('No products in this catalog.') }}</p>
    @endif

    <table style="margin-top: 5mm;">
        @foreach ($rows as $row)
            <tr>
                @foreach ($row as $product)
                    <td class="card" style="width: 49%;">
                        <div class="image">
                            @if ($product['image'])
                                <img src="{{ $product['image'] }}" style="max-height: 46mm; max-width: 80mm;" alt="">
                            @endif
                        </div>
                        <div class="name">{{ $product['name'] }}</div>
                        @if ($product['sku'])
                            <div class="muted"><bdo dir="ltr">{{ $product['sku'] }}</bdo></div>
                        @endif
                        @if ($product['colors'])
                            <div>{{ __('Colors') }}: {{ implode('، ', $product['colors']) }}</div>
                        @endif
                        @if ($product['sizes'])
                            <div>{{ __('Sizes') }}: <bdo dir="ltr">{{ implode(' · ', $product['sizes']) }}</bdo></div>
                        @endif
                        @if ($product['price_label'])
                            <div class="price">
                                @if ($product['price_from']){{ __('From') }} @endif{{ $product['price_label'] }}
                                <span class="muted" style="font-size: 8pt; font-weight: normal;">{{ __('per piece') }}</span>
                            </div>
                            <div class="retail">{{ __('Suggested retail price') }}: {{ $product['retail_label'] }}</div>
                        @else
                            <div class="price">{{ $product['retail_label'] }}</div>
                        @endif
                        @if (! $product['by_piece'] && $product['price_label'])
                            <div class="breaks">{{ __('Sold in full size runs') }}</div>
                        @endif
                        @foreach ($product['breaks'] as $break)
                            <div class="breaks">{{ $break }}</div>
                        @endforeach
                    </td>
                    @if ($loop->first)
                        <td style="width: 2%;"></td>
                    @endif
                @endforeach
                @if (count($row) === 1)
                    <td style="width: 49%;"></td>
                @endif
            </tr>
            <tr><td colspan="3" style="height: 3mm;"></td></tr>
        @endforeach
    </table>

    @if ($doc['truncated'])
        <p class="note">{{ __('Only the first :count products are in this catalog: choose a category to see the rest.', ['count' => $doc['limit']]) }}</p>
    @endif
    @if ($doc['prices'] === 'wholesale')
        <p class="note">{{ __('Wholesale prices for :shop. Prices may change; the price when the order is placed applies.', ['shop' => $doc['shop']]) }}</p>
    @else
        <p class="note">{{ $doc['shop'] }} · {{ __('Prices may change.') }}</p>
    @endif
</body>
</html>

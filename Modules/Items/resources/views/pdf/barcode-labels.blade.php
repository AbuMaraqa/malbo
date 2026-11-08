<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: dejavusans; text-align: center; }
        .code { font-size: 11pt; direction: ltr; margin-top: 2mm; }
        .name { font-size: 15pt; font-weight: bold; margin-top: 3mm; }
    </style>
</head>
<body>
@foreach ($labels as $label)
    <barcode code="{{ $label['code'] }}" type="C128B" size="1.4" height="1.6" />
    <div class="code">{{ $label['code'] }}</div>
    <div class="name">{{ $label['name'] }}</div>
    @unless ($loop->last)
        <pagebreak />
    @endunless
@endforeach
</body>
</html>

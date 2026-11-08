<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\Locales::isRtl() ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link id="app-fonts" rel="stylesheet" href="{{ $fontsUrl }}">
        @if ($faviconUrl)
            <link rel="icon" href="{{ $faviconUrl }}">
        @endif
        @vite(['resources/js/app.ts'])
        @if ($themeCss)
            <style id="app-theme">{!! $themeCss !!}</style>
        @endif
        <script>try{if(localStorage.getItem('theme')==='dark'&&location.pathname.includes('/admin'))document.documentElement.classList.add('dark')}catch(e){}</script>
        @inertiaHead
    </head>
    <body class="dark:bg-gray-900">
        @inertia
    </body>
</html>

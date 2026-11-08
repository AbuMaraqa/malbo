<!DOCTYPE html>
<html lang="ar">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>{{ $store->name['ar'] ?? $store->getTenantKey() }}</title>
        <style>
            body { margin: 0; min-height: 100vh; display: grid; place-content: center; gap: 2rem; padding: 1rem; font-family: system-ui, sans-serif; color: #1b2733; background: #f3f5f7; text-align: center; }
            h1 { font-size: 1.25rem; margin: 0 0 .5rem; }
            p { margin: 0; color: #5a6875; }
        </style>
    </head>
    <body>
        <section lang="ar" dir="rtl">
            <h1>{{ $store->name['ar'] ?? $store->getTenantKey() }}</h1>
            <p>هذا المتجر غير متاح حالياً. يرجى المحاولة لاحقاً.</p>
        </section>
        <section lang="en" dir="ltr">
            <h1>{{ $store->name['en'] ?? $store->getTenantKey() }}</h1>
            <p>This store is currently unavailable. Please try again later.</p>
        </section>
    </body>
</html>

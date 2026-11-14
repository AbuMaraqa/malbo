<?php

return [
    'name' => 'Store',
    'table_prefix' => 'store_',

    // Meta's Graph API version for Conversions API posts (https://graph.facebook.com/v{version}/...).
    'meta' => [
        'graph_version' => env('META_GRAPH_VERSION', '23.0'),
    ],

    // Text messages (cart reminders, back in stock). Only `log` exists: a provider binds
    // Modules\Store\Contracts\SmsGateway.
    'sms' => [
        'driver' => env('STORE_SMS_DRIVER', 'log'),
    ],

    // Instagram content on the storefront. Only `manual` exists (posts entered in the admin): a Graph
    // API connection binds Modules\Store\Contracts\InstagramFeedSource.
    'instagram' => [
        'source' => env('STORE_INSTAGRAM_SOURCE', 'manual'),
    ],

    // `php artisan malbo:demo`: the password of the demo customers and merchants it opens.
    'demo' => [
        'password' => env('MALBO_DEMO_PASSWORD', 'password'),
    ],
];

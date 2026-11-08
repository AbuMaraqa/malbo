<?php

return [
    'available_features' => [
        'core_store' => [
            'name' => 'core_store',
            'display_name' => 'Core Store',
            'description' => 'إدارة الفروع والمواقع والمنتجات والطلبات',
            'icon' => 'solar-shop-2-bold-duotone',
            'color' => 'blue',
            'modules' => [
                'Core',
                'Branch',
                'GeoLocation',
                'Items',
            ],
            'features' => [
                'إدارة المستخدمين والصلاحيات',
                'إدارة الفروع والمواقع',
                'إدارة المنتجات والتصنيفات',
                'إدارة الطلبات',
            ],
            'price' => 299,
            'setup_time' => '1-2 أسبوع',
        ],
    ],

    'enabled_features' => [
        'core_store' => true,
    ],
];

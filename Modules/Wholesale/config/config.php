<?php

return [
    'name' => 'Wholesale',
    'table_prefix' => 'wholesale_',

    // The PDF catalog: bytes of images one PDF may hold (null: CatalogPdfService::IMAGE_BUDGET, 25 MB).
    'catalog_pdf' => [
        'image_budget' => env('WHOLESALE_CATALOG_IMAGE_BUDGET'),
    ],
];

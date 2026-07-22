<?php

return [
    'default_catalog_path' => env(
        'DEFAULT_PRODUCT_CATALOG_PATH',
        storage_path('app/private/catalogs/default_products.csv')
    ),
];

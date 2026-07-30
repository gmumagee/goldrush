<?php

return [
    'enabled' => (bool) env('APP_DEMO_MODE', false),

    'account_slug' => env('DEMO_ACCOUNT_SLUG', 'demo-vending'),
    'shared_user_email' => env('DEMO_SHARED_USER_EMAIL', 'demo@example.com'),
    'shared_user_name' => env('DEMO_SHARED_USER_NAME', 'Public Demo User'),

    'banner_cta_url' => env('DEMO_BANNER_CTA_URL', 'https://goldrushvms.com'),
    'banner_cta_label' => env('DEMO_BANNER_CTA_LABEL', 'See the Real Product'),

    'reset_time' => env('DEMO_RESET_TIME', '04:00'),
];

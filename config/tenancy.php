<?php

return [
    'mode' => env('APP_TENANCY_MODE', 'multi'),
    'single_tenant_account_id' => (int) env('SINGLE_TENANT_ACCOUNT_ID', 1),
];

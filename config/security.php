<?php

return [
    'allow_self_registration' => (bool) env('ALLOW_SELF_REGISTRATION', false),
    'require_verified_email' => (bool) env('REQUIRE_VERIFIED_EMAIL', true),
    'force_https' => (bool) env('FORCE_HTTPS', false),
    'headers_enabled' => (bool) env('SECURITY_HEADERS_ENABLED', true),
];

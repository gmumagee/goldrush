<?php

namespace App\Support;

use RuntimeException;

class Demo
{
    public static function isEnabled(): bool
    {
        return (bool) config('demo.enabled', false);
    }

    public static function accountSlug(): string
    {
        return (string) config('demo.account_slug', 'demo-vending');
    }

    public static function sharedUserEmail(): string
    {
        return mb_strtolower((string) config('demo.shared_user_email', 'demo@example.com'));
    }

    public static function sharedUserName(): string
    {
        return (string) config('demo.shared_user_name', 'Public Demo User');
    }

    public static function bannerCtaUrl(): string
    {
        return (string) config('demo.banner_cta_url', 'https://goldrushvms.com');
    }

    public static function bannerCtaLabel(): string
    {
        return (string) config('demo.banner_cta_label', 'See the Real Product');
    }

    public static function resetTime(): string
    {
        return (string) config('demo.reset_time', '04:00');
    }

    public static function ensureEnabled(string $message = 'Demo mode is disabled.'): void
    {
        if (! self::isEnabled()) {
            throw new RuntimeException($message);
        }
    }
}

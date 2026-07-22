<?php

namespace App\Support;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class Tenancy
{
    public const MODE_MULTI = 'multi';
    public const MODE_SINGLE = 'single';

    public static function mode(): string
    {
        $mode = (string) config('tenancy.mode', self::MODE_MULTI);

        return in_array($mode, [self::MODE_MULTI, self::MODE_SINGLE], true)
            ? $mode
            : self::MODE_MULTI;
    }

    public static function isSingle(): bool
    {
        return self::mode() === self::MODE_SINGLE;
    }

    public static function isMulti(): bool
    {
        return self::mode() === self::MODE_MULTI;
    }

    public static function singleAccountId(): int
    {
        return max(1, (int) config('tenancy.single_tenant_account_id', 1));
    }

    public static function currentAccountId(?Request $request = null): ?int
    {
        $request ??= request();

        if (! $request instanceof Request) {
            return self::isSingle() ? self::singleAccountId() : null;
        }

        $accountId = (int) $request->session()->get('current_account_id');

        if ($accountId > 0) {
            return $accountId;
        }

        if (! self::isSingle()) {
            return null;
        }

        return self::pinSingleAccountInSession($request);
    }

    public static function pinSingleAccountInSession(Request $request): int
    {
        $accountId = self::singleAccountId();
        $request->session()->put('current_account_id', $accountId);

        return $accountId;
    }

    public static function singleAccount(): ?Account
    {
        return Account::query()->find(self::singleAccountId());
    }

    public static function hasSingleAccount(): bool
    {
        return self::singleAccount() !== null;
    }

    public static function ensureSingleAccount(string $accountName, ?string $billingEmail = null): Account
    {
        $account = self::singleAccount();

        if ($account) {
            return $account;
        }

        $account = new Account();
        $account->forceFill([
            'id' => self::singleAccountId(),
            'account_name' => $accountName,
            'slug' => self::generateUniqueAccountSlug($accountName),
            'status' => Account::STATUS_ACTIVE,
            'billing_email' => $billingEmail ?: self::defaultBillingEmail(),
        ]);
        $account->save();

        return $account;
    }

    public static function requireSingleAccount(): Account
    {
        $account = self::singleAccount();

        if ($account) {
            return $account;
        }

        throw new RuntimeException(sprintf(
            'Single-tenant mode is enabled, but account #%d does not exist. Run "php artisan tenancy:init-single \"%s\"" first.',
            self::singleAccountId(),
            config('app.name', 'GoldRush')
        ));
    }

    private static function generateUniqueAccountSlug(string $accountName): string
    {
        $baseSlug = Str::slug($accountName) ?: 'account';
        $slug = $baseSlug;
        $counter = 2;

        while (Account::query()
            ->where('slug', $slug)
            ->whereKeyNot(self::singleAccountId())
            ->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private static function defaultBillingEmail(): string
    {
        $mailFrom = config('mail.from.address');

        return is_string($mailFrom) && $mailFrom !== ''
            ? $mailFrom
            : 'billing@example.com';
    }
}

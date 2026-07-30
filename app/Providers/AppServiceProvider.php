<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\AuditLog;
use App\Models\Bin;
use App\Models\CalendarEvent;
use App\Models\CalendarReminder;
use App\Models\Contact;
use App\Models\DataDictionary;
use App\Models\Location;
use App\Models\LocationContact;
use App\Models\LocationDocument;
use App\Models\Machine;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\RouteLocation;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\VendingRoute;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Observers\AccountObserver;
use App\Policies\AccountUserPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\DataDictionaryPolicy;
use App\Policies\OperationalEntityPolicy;
use App\Policies\ServicePolicy;
use App\Support\Demo;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (Demo::isEnabled()) {
            config(['mail.default' => 'array']);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('security.force_https', false)) {
            URL::forceScheme('https');
        }

        RateLimiter::for('login', function ($request) {
            $email = mb_strtolower(trim((string) $request->input('email')));

            return Limit::perMinute(5)->by(($email !== '' ? $email : 'guest').'|'.$request->ip());
        });

        RateLimiter::for('register', fn ($request) => [
            Limit::perMinutes(10, 3)->by($request->ip()),
        ]);

        RateLimiter::for('password-confirm', fn ($request) => [
            Limit::perMinute(6)->by(($request->user()?->id ?? 'guest').'|'.$request->ip()),
        ]);

        RateLimiter::for('verification-resend', fn ($request) => [
            Limit::perMinutes(10, 3)->by(($request->user()?->id ?? 'guest').'|'.$request->ip()),
        ]);

        RateLimiter::for('admin-backups', fn ($request) => [
            Limit::perMinutes(10, 2)->by((string) ($request->user()?->id ?? 'guest')),
        ]);

        RateLimiter::for('admin-backup-downloads', fn ($request) => [
            Limit::perMinute(10)->by((string) ($request->user()?->id ?? 'guest')),
        ]);

        Account::observe(AccountObserver::class);

        foreach ([
            Bin::class,
            CalendarEvent::class,
            CalendarReminder::class,
            Contact::class,
            Location::class,
            LocationContact::class,
            LocationDocument::class,
            Machine::class,
            Product::class,
            Purchase::class,
            RouteLocation::class,
            Transaction::class,
            VendingRoute::class,
            Vendor::class,
            Warehouse::class,
        ] as $modelClass) {
            Gate::policy($modelClass, OperationalEntityPolicy::class);
        }

        Gate::policy(Service::class, ServicePolicy::class);
        Gate::policy(AccountUser::class, AccountUserPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(DataDictionary::class, DataDictionaryPolicy::class);
    }
}

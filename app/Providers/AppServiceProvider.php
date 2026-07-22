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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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

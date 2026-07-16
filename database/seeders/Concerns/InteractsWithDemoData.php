<?php

namespace Database\Seeders\Concerns;

use App\Models\Account;
use App\Models\Machine;
use App\Models\Product;
use App\Models\RouteLocation;
use App\Models\Service;
use App\Models\User;
use App\Models\VendingRoute;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\Location;
use Illuminate\Database\Eloquent\ModelNotFoundException;

trait InteractsWithDemoData
{
    protected const DEMO_ACCOUNT_SLUG = 'demo-vending';
    protected const OTHER_ACCOUNT_SLUG = 'other-vending';

    protected function demoAccount(): Account
    {
        return $this->accountBySlug(self::DEMO_ACCOUNT_SLUG);
    }

    protected function otherAccount(): Account
    {
        return $this->accountBySlug(self::OTHER_ACCOUNT_SLUG);
    }

    protected function accountBySlug(string $slug): Account
    {
        return Account::query()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    protected function userByEmail(string $email): User
    {
        return User::query()
            ->where('email', $email)
            ->firstOrFail();
    }

    protected function warehouseForAccount(int $accountId, string $warehouseName): Warehouse
    {
        return Warehouse::query()
            ->where('account_id', $accountId)
            ->where('warehouse_name', $warehouseName)
            ->firstOrFail();
    }

    protected function vendorForAccount(int $accountId, string $vendorName): Vendor
    {
        return Vendor::query()
            ->where('account_id', $accountId)
            ->where('vendor_name', $vendorName)
            ->firstOrFail();
    }

    protected function productForAccount(int $accountId, string $sku): Product
    {
        return Product::query()
            ->where('account_id', $accountId)
            ->where('sku', $sku)
            ->firstOrFail();
    }

    protected function routeForAccount(int $accountId, string $routeName): VendingRoute
    {
        return VendingRoute::query()
            ->where('account_id', $accountId)
            ->where('route_name', $routeName)
            ->firstOrFail();
    }

    protected function locationForAccount(int $accountId, string $locationName): Location
    {
        return Location::query()
            ->where('account_id', $accountId)
            ->where('location_name', $locationName)
            ->firstOrFail();
    }

    protected function machineForAccount(int $accountId, string $serialNumber): Machine
    {
        return Machine::query()
            ->where('account_id', $accountId)
            ->where('serial_number', $serialNumber)
            ->firstOrFail();
    }

    protected function serviceForAccountByLocationAndDate(int $accountId, string $locationName, string $serviceDate): Service
    {
        $location = $this->locationForAccount($accountId, $locationName);

        return Service::query()
            ->where('account_id', $accountId)
            ->where('location_id', $location->id)
            ->whereDate('service_date', $serviceDate)
            ->firstOrFail();
    }

    protected function routeLocationForAccount(int $accountId, int $routeId, int $locationId): RouteLocation
    {
        $routeLocation = RouteLocation::query()
            ->where('account_id', $accountId)
            ->where('route_id', $routeId)
            ->where('location_id', $locationId)
            ->first();

        if (! $routeLocation) {
            throw new ModelNotFoundException('Route location was not found.');
        }

        return $routeLocation;
    }
}

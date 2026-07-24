<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

class Location extends Model
{
    public const INVENTORY_LOCATION_NAME = 'Inventory';

    protected $table = 'tbl_locations';

    public $timestamps = false;

    protected $fillable = [
        'account_id',
        'location_name',
        'address',
        'city',
        'state',
        'zip_code',
        'is_inventory',
    ];

    protected $casts = [
        'is_inventory' => 'boolean',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function scopeInventory(Builder $query): Builder
    {
        return $query->where('is_inventory', true);
    }

    public function scopeNotInventory(Builder $query): Builder
    {
        return $query->whereNull('is_inventory');
    }

    public function isInventory(): bool
    {
        return (bool) $this->is_inventory;
    }

    public static function ensureInventoryLocationForAccount(int $accountId, string $locationName = self::INVENTORY_LOCATION_NAME): self
    {
        $existingLocation = static::query()
            ->where('account_id', $accountId)
            ->inventory()
            ->first();

        if ($existingLocation) {
            return $existingLocation;
        }

        try {
            return static::create([
                'account_id' => $accountId,
                'location_name' => $locationName,
                'address' => null,
                'city' => null,
                'state' => null,
                'zip_code' => null,
                'is_inventory' => true,
            ]);
        } catch (QueryException $exception) {
            $existingLocation = static::query()
                ->where('account_id', $accountId)
                ->inventory()
                ->first();

            if ($existingLocation) {
                return $existingLocation;
            }

            throw $exception;
        }
    }

    public function routeLocations()
    {
        return $this->hasMany(RouteLocation::class, 'location_id');
    }

    public function primaryRouteLocation()
    {
        return $this->hasOne(RouteLocation::class, 'location_id')
            ->where('is_primary', true)
            ->orderBy('id');
    }

    public function routes()
    {
        return $this->belongsToMany(VendingRoute::class, 'tbl_route_locations', 'location_id', 'route_id')
            ->withPivot(['id', 'account_id', 'stop_order', 'is_primary'])
            ->orderBy('tbl_route_locations.stop_order')
            ->orderBy('tbl_route_locations.id');
    }

    public function machines()
    {
        return $this->hasMany(Machine::class, 'location_id');
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'location_id');
    }

    public function calendarEvents()
    {
        return $this->hasMany(CalendarEvent::class, 'location_id')
            ->orderBy('start_at');
    }

    public function locationContacts()
    {
        return $this->hasMany(LocationContact::class, 'location_id')
            ->orderByDesc('is_primary')
            ->orderBy('id');
    }

    public function primaryLocationContact()
    {
        return $this->hasOne(LocationContact::class, 'location_id')
            ->where('is_primary', true)
            ->latest('id');
    }

    public function documents()
    {
        return $this->hasMany(LocationDocument::class, 'location_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function contacts()
    {
        return $this->belongsToMany(Contact::class, 'tbl_location_contacts', 'location_id', 'contact_id')
            ->withPivot(['id', 'account_id', 'contact_role', 'is_primary', 'notes']);
    }

    public function transactions()
    {
        return $this->hasManyThrough(
            Transaction::class,
            Machine::class,
            'location_id',
            'machine_id',
            'id',
            'id'
        );
    }

    public function serviceSales()
    {
        return $this->hasMany(ServiceSale::class, 'location_id');
    }
}

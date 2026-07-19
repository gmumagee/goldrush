<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $table = 'tbl_locations';

    public $timestamps = false;

    protected $fillable = [
        'account_id',
        'route_id',
        'location_name',
        'address',
        'city',
        'state',
        'zip_code',
        'contact_name',
        'contact_phone',
        'contact_email',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function route()
    {
        return $this->belongsTo(VendingRoute::class, 'route_id');
    }

    public function routeLocations()
    {
        return $this->hasMany(RouteLocation::class, 'location_id');
    }

    public function routes()
    {
        return $this->belongsToMany(VendingRoute::class, 'tbl_route_locations', 'location_id', 'route_id')
            ->withPivot(['id', 'account_id', 'stop_order']);
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

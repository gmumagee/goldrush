<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendingRoute extends Model
{
    protected $withCount = [];

    protected $table = 'tbl_routes';

    public $timestamps = false;

    protected $fillable = [
        'account_id',
        'route_name',
        'description',
        'scheduled_day',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function routeLocations()
    {
        return $this->hasMany(RouteLocation::class, 'route_id')
            ->orderBy('stop_order')
            ->orderBy('id');
    }

    public function locations()
    {
        return $this->belongsToMany(Location::class, 'tbl_route_locations', 'route_id', 'location_id')
            ->withPivot(['id', 'account_id', 'stop_order'])
            ->orderBy('tbl_route_locations.stop_order')
            ->orderBy('tbl_route_locations.id');
    }

    public function calendarEvents()
    {
        return $this->hasMany(CalendarEvent::class, 'route_id')
            ->orderBy('start_at');
    }
}

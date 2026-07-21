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
        'warehouse_id',
        'assigned_user_id',
        'auto_schedule_enabled',
    ];

    protected $casts = [
        'auto_schedule_enabled' => 'boolean',
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

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function locations()
    {
        return $this->belongsToMany(Location::class, 'tbl_route_locations', 'route_id', 'location_id')
            ->withPivot(['id', 'account_id', 'stop_order', 'is_primary'])
            ->orderBy('tbl_route_locations.stop_order')
            ->orderBy('tbl_route_locations.id');
    }

    public function calendarEvents()
    {
        return $this->hasMany(CalendarEvent::class, 'route_id')
            ->orderBy('start_at');
    }
}

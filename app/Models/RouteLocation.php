<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RouteLocation extends Model
{
    protected $table = 'tbl_route_locations';

    public $timestamps = true;

    public const UPDATED_AT = null;

    protected $fillable = [
        'account_id',
        'route_id',
        'location_id',
        'stop_order',
    ];

    protected $casts = [
        'account_id' => 'integer',
        'route_id' => 'integer',
        'location_id' => 'integer',
        'stop_order' => 'integer',
        'created_at' => 'datetime',
    ];

    public function route()
    {
        return $this->belongsTo(VendingRoute::class, 'route_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }
}

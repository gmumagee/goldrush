<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocationAccessHour extends Model
{
    protected $table = 'tbl_location_access_hours';

    public $timestamps = false;

    protected $fillable = [
        'account_id',
        'location_id',
        'day_of_week',
        'opens_at',
        'closes_at',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }
}

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

    public function machines()
    {
        return $this->hasMany(Machine::class, 'location_id');
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'location_id');
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
}

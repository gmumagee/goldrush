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
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function locations()
    {
        return $this->hasMany(Location::class, 'route_id');
    }
}

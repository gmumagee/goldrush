<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $table = 'tbl_warehouses';

    public $timestamps = false;

    protected $fillable = [
        'account_id',
        'warehouse_name',
        'address',
        'city',
        'state',
        'zip_code',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}

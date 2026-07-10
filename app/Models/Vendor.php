<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $table = 'tbl_vendors';

    public $timestamps = false;

    protected $fillable = [
        'account_id',
        'vendor_name',
        'location',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'vendor_id');
    }
}

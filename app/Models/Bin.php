<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bin extends Model
{
    protected $table = 'tbl_bins';

    public $timestamps = false;

    protected $fillable = [
        'account_id',
        'machine_id',
        'product_id',
        'bin_code',
        'capacity',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'bin_id');
    }
}

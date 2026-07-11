<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $table = 'tbl_transactions';

    public $timestamps = false;

    protected $fillable = [
        'account_id',
        'service_id',
        'machine_id',
        'bin_id',
        'product_id',
        'transaction_type',
        'quantity',
        'transaction_at',
        'price',
        'unit_cost',
    ];

    protected $casts = [
        'transaction_at' => 'datetime',
        'price' => 'decimal:2',
        'unit_cost' => 'decimal:2',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function bin()
    {
        return $this->belongsTo(Bin::class, 'bin_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}

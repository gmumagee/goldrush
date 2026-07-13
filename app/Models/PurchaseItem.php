<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    protected $table = 'tbl_purchase_items';

    public $timestamps = true;

    public const UPDATED_AT = null;

    protected $fillable = [
        'account_id',
        'purchase_id',
        'product_id',
        'quantity',
        'line_total',
        'unit_cost',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'line_total' => 'decimal:2',
        'unit_cost' => 'decimal:4',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}

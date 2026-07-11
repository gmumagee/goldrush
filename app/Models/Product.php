<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use BelongsToAccount;
   
    protected $table = 'tbl_products';

    public $timestamps = false;

    protected $fillable = [
        'account_id',
        'vendor_id',
        'category',
        'brand',
        'sku',
        'product_name',
        'size',
        'package_type',
        'barcode',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function bins()
    {
        return $this->hasMany(Bin::class, 'product_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'product_id');
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Builder;
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

    public function scopeOrderedForDropdown(Builder $query): Builder
    {
        return $query
            ->orderByRaw("LOWER(TRIM(product_name))")
            ->orderByRaw("CASE WHEN size IS NULL OR TRIM(size) = '' THEN 1 ELSE 0 END")
            ->orderByRaw("LOWER(TRIM(COALESCE(size, '')))")
            ->orderByRaw("CASE WHEN package_type IS NULL OR TRIM(package_type) = '' THEN 1 ELSE 0 END")
            ->orderByRaw("LOWER(TRIM(COALESCE(package_type, '')))");
    }

    public function getDisplayNameAttribute(): string
    {
        return collect([
            $this->product_name,
            $this->size,
            $this->package_type,
        ])
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->filter(fn ($value) => is_string($value) ? $value !== '' : ! is_null($value))
            ->implode(' · ');
    }

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

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class, 'product_id');
    }

    public function inventoryLedger()
    {
        return $this->hasMany(InventoryLedger::class, 'product_id');
    }

    public function serviceSales()
    {
        return $this->hasMany(ServiceSale::class, 'product_id');
    }
}

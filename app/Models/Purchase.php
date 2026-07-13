<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    public const STATUS_POSTED = 'Posted';
    public const STATUS_VOIDED = 'Voided';

    protected $table = 'tbl_purchases';

    public $timestamps = true;

    public const UPDATED_AT = null;

    protected $fillable = [
        'account_id',
        'vendor_id',
        'warehouse_id',
        'invoice_number',
        'purchase_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function items()
    {
        return $this->hasMany(PurchaseItem::class, 'purchase_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function isPosted(): bool
    {
        return strcasecmp(trim((string) $this->status), self::STATUS_POSTED) === 0;
    }

    public function isVoided(): bool
    {
        return strcasecmp(trim((string) $this->status), self::STATUS_VOIDED) === 0;
    }
}

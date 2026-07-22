<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class InventoryLedger extends Model
{
    use Auditable;

    public const MOVEMENT_TYPE_PURCHASE = 'purchase';
    public const MOVEMENT_TYPE_PURCHASE_VOID = 'purchase_void';
    public const MOVEMENT_TYPE_SERVICE_FILL = 'service_fill';
    public const MOVEMENT_TYPE_ADJUSTMENT = 'adjustment';

    protected $table = 'tbl_inventory_ledger';

    public $timestamps = true;

    public const UPDATED_AT = null;

    protected $fillable = [
        'account_id',
        'warehouse_id',
        'product_id',
        'movement_type',
        'quantity_delta',
        'unit_cost',
        'total_cost',
        'source_type',
        'source_id',
        'movement_at',
        'notes',
    ];

    protected $casts = [
        'quantity_delta' => 'integer',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'movement_at' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    protected function shouldAuditEvent(string $event): bool
    {
        return $event !== AuditLog::EVENT_UPDATED;
    }
}

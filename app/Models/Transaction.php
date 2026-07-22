<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use Auditable;

    // Reuse canonical transaction types so reconciliation does not depend on literal strings.
    public const TYPE_CURRENT_INVENTORY = 'current_inventory';
    public const TYPE_COUNT = 'count';
    public const TYPE_FILL = 'fill';
    public const TYPE_ADD = 'add';
    public const TYPE_WASTE = 'waste';
    public const TYPE_REMOVE = 'remove';
    public const TYPE_ADJUSTMENT = 'adjustment';

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
        'spoilage',
        'transaction_at',
        'price',
        'unit_cost',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'spoilage' => 'integer',
        'transaction_at' => 'datetime',
        'price' => 'decimal:2',
        'unit_cost' => 'decimal:4',
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

    public static function movementTypesForSales(): array
    {
        // Centralize the movement set so calculators and reports reconcile the same evidence rows.
        return [
            self::TYPE_CURRENT_INVENTORY,
            self::TYPE_COUNT,
            self::TYPE_FILL,
            self::TYPE_ADD,
            self::TYPE_WASTE,
            self::TYPE_REMOVE,
            self::TYPE_ADJUSTMENT,
        ];
    }
}

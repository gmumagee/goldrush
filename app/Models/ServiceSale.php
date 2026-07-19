<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceSale extends Model
{
    use BelongsToAccount;

    public const CALCULATION_CALCULATED = 'calculated';
    public const CALCULATION_BASELINE = 'baseline';

    protected $table = 'tbl_service_sales';

    protected $fillable = [
        'account_id',
        'service_id',
        'location_id',
        'machine_id',
        'bin_id',
        'product_id',
        'previous_inventory_transaction_id',
        'count_transaction_id',
        'calculation_status',
        'calculation_note',
        'sales_date',
        'opening_quantity',
        'inventory_additions',
        'non_sale_removals',
        'counted_quantity',
        'units_sold',
        'unit_price',
        'sales_amount',
        'calculation_version',
        'calculated_at',
    ];

    protected $casts = [
        'sales_date' => 'date',
        'opening_quantity' => 'integer',
        'inventory_additions' => 'integer',
        'non_sale_removals' => 'integer',
        'counted_quantity' => 'integer',
        'units_sold' => 'integer',
        'unit_price' => 'decimal:2',
        'sales_amount' => 'decimal:2',
        'calculated_at' => 'datetime',
    ];

    public function isCalculated(): bool
    {
        return strcasecmp(trim((string) $this->calculation_status), self::CALCULATION_CALCULATED) === 0;
    }

    public function isBaseline(): bool
    {
        return strcasecmp(trim((string) $this->calculation_status), self::CALCULATION_BASELINE) === 0;
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'bin_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function previousInventoryTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'previous_inventory_transaction_id');
    }

    public function countTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'count_transaction_id');
    }
}

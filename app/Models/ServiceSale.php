<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceSale extends Model
{
    use BelongsToAccount;

    // Distinguish reportable sales rows from first-service baseline rows.
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
        'spoilage',
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
        'spoilage' => 'integer',
        'counted_quantity' => 'integer',
        'units_sold' => 'integer',
        'unit_price' => 'decimal:2',
        'sales_amount' => 'decimal:2',
        'calculated_at' => 'datetime',
    ];

    public function isCalculated(): bool
    {
        // Compare case-insensitively so historical rows do not break revenue filtering.
        return strcasecmp(trim((string) $this->calculation_status), self::CALCULATION_CALCULATED) === 0;
    }

    public function isBaseline(): bool
    {
        // Baseline rows establish inventory history but should not count as revenue.
        return strcasecmp(trim((string) $this->calculation_status), self::CALCULATION_BASELINE) === 0;
    }

    public function getCalculationStatusLabelAttribute(): string
    {
        // Map stored sales statuses to clearer public-facing labels without changing persisted values.
        return match (strtolower(trim((string) $this->calculation_status))) {
            self::CALCULATION_BASELINE => 'Initial Installation',
            self::CALCULATION_CALCULATED => 'Calculated',
            default => ucfirst(str_replace('_', ' ', trim((string) $this->calculation_status))),
        };
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
        // Keep the opening inventory snapshot attached for auditability.
        return $this->belongsTo(Transaction::class, 'previous_inventory_transaction_id');
    }

    public function countTransaction(): BelongsTo
    {
        // Keep the final count attached so each sales line can be explained later.
        return $this->belongsTo(Transaction::class, 'count_transaction_id');
    }
}

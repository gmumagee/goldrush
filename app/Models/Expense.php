<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use Auditable;
    use BelongsToAccount;

    public const CATEGORY_FUEL = 'fuel';
    public const CATEGORY_MAINTENANCE = 'maintenance';
    public const CATEGORY_RENT = 'rent';
    public const CATEGORY_SUPPLIES = 'supplies';
    public const CATEGORY_INSURANCE = 'insurance';
    public const CATEGORY_UTILITIES = 'utilities';
    public const CATEGORY_VEHICLE = 'vehicle';
    public const CATEGORY_OTHER = 'other';

    protected $table = 'tbl_expenses';

    public $timestamps = false;

    protected $fillable = [
        'location_id',
        'category',
        'amount',
        'expense_date',
        'description',
        'vendor',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    public static function categories(): array
    {
        return array_keys(self::categoryOptions());
    }

    public static function categoryOptions(): array
    {
        return [
            self::CATEGORY_FUEL => 'Fuel',
            self::CATEGORY_MAINTENANCE => 'Maintenance',
            self::CATEGORY_RENT => 'Rent',
            self::CATEGORY_SUPPLIES => 'Supplies',
            self::CATEGORY_INSURANCE => 'Insurance',
            self::CATEGORY_UTILITIES => 'Utilities',
            self::CATEGORY_VEHICLE => 'Vehicle',
            self::CATEGORY_OTHER => 'Other',
        ];
    }

    public function scopeGeneral(Builder $query): Builder
    {
        return $query->whereNull('location_id');
    }

    public function scopeForLocation(Builder $query, int $locationId): Builder
    {
        return $query->where('location_id', $locationId);
    }

    public function isGeneral(): bool
    {
        return $this->location_id === null;
    }

    public function categoryLabel(): string
    {
        return self::categoryOptions()[$this->category] ?? ucfirst((string) $this->category);
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}

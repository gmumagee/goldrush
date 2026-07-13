<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DataDictionary extends Model
{
    public const GROUP_SERVICE_STATUS = 'service_status';
    public const GROUP_PURCHASE_STATUS = 'purchase_status';
    public const GROUP_INVENTORY_MOVEMENT_TYPE = 'inventory_movement_type';
    public const GROUP_MACHINE_STATUS = 'machine_status';
    public const GROUP_ACCOUNT_STATUS = 'account_status';
    public const GROUP_ROUTE_SCHEDULED_DAY = 'route_scheduled_day';
    public const GROUP_USER_STATUS = 'user_status';
    public const GROUP_ACCOUNT_USER_ROLE = 'account_user_role';
    public const GROUP_ACCOUNT_USER_STATUS = 'account_user_status';

    protected $table = 'tbl_data_dictionary';

    protected $fillable = [
        'account_id',
        'name',
        'value',
        'label',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'account_id' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function scopeForGroup(Builder $query, string $group): Builder
    {
        // Dictionary groups keep related status values together for reuse.
        return $query->where('name', $group);
    }

    public function scopeActive(Builder $query): Builder
    {
        // Only active dictionary values should be selectable in the UI.
        return $query->where('is_active', true);
    }

    public function scopeForAccountScope(Builder $query, ?int $accountId): Builder
    {
        // Global values stay available while still allowing future
        // account-specific overrides to coexist in the same table.
        return $query->where(function (Builder $dictionaryQuery) use ($accountId) {
            $dictionaryQuery->whereNull('account_id');

            if ($accountId !== null) {
                $dictionaryQuery->orWhere('account_id', $accountId);
            }
        });
    }

    public function displayLabel(): string
    {
        // Fall back to the stored value so older rows still display cleanly.
        return $this->label ?: $this->value;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    public const FREE_ID = 1;
    public const STARTER_ID = 2;
    public const PRO_ID = 3;

    public const FREE_SLUG = 'free';
    public const STARTER_SLUG = 'starter';
    public const PRO_SLUG = 'pro';

    protected $table = 'plans';

    protected $fillable = [
        'name',
        'slug',
        'machine_limit',
        'display_price',
        'sort_order',
    ];

    protected $casts = [
        'machine_limit' => 'integer',
        'sort_order' => 'integer',
    ];

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function supportsMachineCount(int $machineCount): bool
    {
        return $this->machine_limit === null || $machineCount <= $this->machine_limit;
    }

    public function isUnlimited(): bool
    {
        return $this->machine_limit === null;
    }

    public function machineLimitLabel(): string
    {
        return $this->isUnlimited() ? 'Unlimited' : (string) $this->machine_limit;
    }
}

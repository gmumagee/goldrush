<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $table = 'tbl_accounts';

    public $timestamps = false;

    protected $fillable = [
        'plan_id',
        'account_name',
        'slug',
        'status',
        'billing_email',
        'phone',
    ];

    protected static function booted(): void
    {
        static::creating(function (Account $account): void {
            if ((int) $account->plan_id <= 0) {
                $account->plan_id = Plan::FREE_ID;
            }
        });
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'tbl_account_users', 'account_id', 'user_id')
            ->withPivot(['role', 'status']);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function accountUsers()
    {
        return $this->hasMany(AccountUser::class, 'account_id');
    }

    public function warehouses()
    {
        return $this->hasMany(Warehouse::class, 'account_id');
    }

    public function vendors()
    {
        return $this->hasMany(Vendor::class, 'account_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'account_id');
    }

    public function routes()
    {
        return $this->hasMany(VendingRoute::class, 'account_id');
    }

    public function locations()
    {
        return $this->hasMany(Location::class, 'account_id');
    }

    public function inventoryLocation()
    {
        return $this->hasOne(Location::class, 'account_id')->inventory();
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class, 'account_id');
    }

    public function machines()
    {
        return $this->hasMany(Machine::class, 'account_id');
    }

    public function machineCount(): int
    {
        if (array_key_exists('machine_count', $this->attributes)) {
            return (int) $this->attributes['machine_count'];
        }

        if (array_key_exists('machines_count', $this->attributes)) {
            return (int) $this->attributes['machines_count'];
        }

        if ($this->relationLoaded('machines')) {
            return $this->machines->count();
        }

        return (int) $this->machines()->count();
    }

    public function isOverMachineLimit(): bool
    {
        $this->loadMissing('plan');

        return $this->plan?->machine_limit !== null
            && $this->machineCount() > $this->plan->machine_limit;
    }

    public function backups()
    {
        return $this->hasMany(AccountBackup::class, 'account_id');
    }

    public function bins()
    {
        return $this->hasMany(Bin::class, 'account_id');
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'account_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'account_id');
    }

    public function planUpgradeRequests()
    {
        return $this->hasMany(PlanUpgradeRequest::class, 'account_id');
    }
}

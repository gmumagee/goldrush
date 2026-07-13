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
        'account_name',
        'slug',
        'status',
        'billing_email',
        'phone',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'tbl_account_users', 'account_id', 'user_id')
            ->withPivot(['role', 'status']);
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

    public function machines()
    {
        return $this->hasMany(Machine::class, 'account_id');
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
}

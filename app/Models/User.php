<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $table = 'tbl_users';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'email_verified_at',
        'remember_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function accounts()
    {
        return $this->belongsToMany(Account::class, 'tbl_account_users', 'user_id', 'account_id')
            ->withPivot(['role', 'status']);
    }

    public function accountMemberships()
    {
        return $this->hasMany(AccountUser::class, 'user_id');
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'user_id');
    }
}

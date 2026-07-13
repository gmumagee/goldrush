<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountUser extends Model
{
    public const ROLE_OWNER = 'Owner';
    public const ROLE_ADMIN = 'Admin';
    public const ROLE_MANAGER = 'Manager';
    public const ROLE_TECHNICIAN = 'Technician';
    public const ROLE_VIEWER = 'Viewer';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $table = 'tbl_account_users';

    public $timestamps = false;

    protected $fillable = [
        'account_id',
        'user_id',
        'role',
        'status',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isActive(): bool
    {
        return strcasecmp(trim((string) $this->status), self::STATUS_ACTIVE) === 0;
    }

    public function roleMatches(string $role): bool
    {
        return strcasecmp(trim((string) $this->role), $role) === 0;
    }

    public function isOwner(): bool
    {
        return $this->roleMatches(self::ROLE_OWNER);
    }

    public function canManageAccountUsers(): bool
    {
        return $this->roleMatches(self::ROLE_OWNER) || $this->roleMatches(self::ROLE_ADMIN);
    }
}

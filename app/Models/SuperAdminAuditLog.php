<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuperAdminAuditLog extends Model
{
    protected $table = 'tbl_super_admin_audit_log';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'account_id',
        'action',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}

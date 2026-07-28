<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountBackup extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';

    protected $table = 'tbl_account_backups';

    protected $fillable = [
        'account_id',
        'requested_by_user_id',
        'status',
        'file_disk',
        'file_path',
        'file_name',
        'file_size_bytes',
        'row_counts',
        'failure_message',
        'ready_at',
        'failed_at',
    ];

    protected $casts = [
        'row_counts' => 'array',
        'ready_at' => 'datetime',
        'failed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }
}

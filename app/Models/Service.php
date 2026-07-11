<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    public const STATUS_AWAITING_SERVICE = 'Awaiting Service';
    public const STATUS_SERVICE_OPEN = 'Service Open';
    public const STATUS_SERVICE_CLOSED = 'Service Closed';
    public const TYPE_LOCATION_SERVICE = 'location_service';

    protected $table = 'tbl_services';

    public $timestamps = false;

    protected $fillable = [
        'account_id',
        'location_id',
        'user_id',
        'service_type',
        'service_date',
        'opened_at',
        'closed_at',
        'status',
    ];

    protected $casts = [
        'service_date' => 'datetime',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'service_id');
    }

    public function isAwaitingService(): bool
    {
        return $this->status === self::STATUS_AWAITING_SERVICE;
    }

    public function isServiceOpen(): bool
    {
        return $this->status === self::STATUS_SERVICE_OPEN;
    }

    public function isServiceClosed(): bool
    {
        return $this->status === self::STATUS_SERVICE_CLOSED;
    }
}

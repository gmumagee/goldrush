<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    public const STATUS_AWAITING_SERVICE = 'Awaiting Service';
    public const STATUS_SERVICE_OPEN = 'Service Open';
    public const STATUS_SERVICE_COMPLETED = 'Service Completed';
    public const STATUS_SERVICE_CLOSED = 'Service Closed';
    public const TYPE_LOCATION_SERVICE = 'location_service';

    protected $table = 'tbl_services';

    public $timestamps = false;

    protected $fillable = [
        'account_id',
        'location_id',
        'warehouse_id',
        'user_id',
        'closed_by_user_id',
        'service_type',
        'service_date',
        'scheduled_at',
        'opened_at',
        'completed_at',
        'closed_at',
        'amount_collected',
        'status',
    ];

    protected $casts = [
        'service_date' => 'date',
        'scheduled_at' => 'datetime',
        'opened_at' => 'datetime',
        'completed_at' => 'datetime',
        'closed_at' => 'datetime',
        'amount_collected' => 'decimal:2',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'service_id');
    }

    public function calendarEvents()
    {
        return $this->hasMany(CalendarEvent::class, 'source_id')
            ->where('source_type', CalendarEvent::SOURCE_TYPE_SERVICE);
    }

    public function isAwaitingService(): bool
    {
        // Normalize legacy status casing so older rows still follow the
        // current service workflow rules.
        return $this->statusMatches(self::STATUS_AWAITING_SERVICE);
    }

    public function isServiceOpen(): bool
    {
        // Normalize status comparisons so workflow checks stay consistent.
        return $this->statusMatches(self::STATUS_SERVICE_OPEN);
    }

    public function isServiceCompleted(): bool
    {
        // Normalize status comparisons so workflow checks stay consistent.
        return $this->statusMatches(self::STATUS_SERVICE_COMPLETED);
    }

    public function isServiceClosed(): bool
    {
        // Normalize status comparisons so workflow checks stay consistent.
        return $this->statusMatches(self::STATUS_SERVICE_CLOSED);
    }

    protected function statusMatches(string $expectedStatus): bool
    {
        // Trim and compare case-insensitively because historical rows may not
        // match the current canonical status capitalization.
        return strcasecmp(trim((string) $this->status), $expectedStatus) === 0;
    }
}

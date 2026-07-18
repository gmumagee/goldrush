<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class CalendarEvent extends Model
{
    use BelongsToAccount;

    public const STATUS_SCHEDULED = 'Scheduled';
    public const STATUS_COMPLETED = 'Completed';
    public const STATUS_CANCELLED = 'Cancelled';

    public const SOURCE_TYPE_SERVICE = 'service';
    public const SOURCE_TYPE_PURCHASE = 'purchase';
    public const SOURCE_TYPE_ROUTE = 'route';
    public const SOURCE_TYPE_LOCATION = 'location';
    public const SOURCE_TYPE_WAREHOUSE = 'warehouse';

    protected $table = 'tbl_calendar_events';

    protected $fillable = [
        'account_id',
        'event_type',
        'title',
        'description',
        'start_at',
        'end_at',
        'all_day',
        'status',
        'priority',
        'assigned_user_id',
        'location_id',
        'warehouse_id',
        'route_id',
        'source_type',
        'source_id',
        'created_by_user_id',
        'completed_at',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'all_day' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function reminders()
    {
        return $this->hasMany(CalendarReminder::class, 'calendar_event_id')
            ->orderBy('remind_at')
            ->orderBy('id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function route()
    {
        return $this->belongsTo(VendingRoute::class, 'route_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->isScheduled()
            && $this->start_at !== null
            && $this->start_at->lt(now());
    }

    public function isScheduled(): bool
    {
        return $this->statusMatches(self::STATUS_SCHEDULED);
    }

    public function isCompleted(): bool
    {
        return $this->statusMatches(self::STATUS_COMPLETED);
    }

    public function isCancelled(): bool
    {
        return $this->statusMatches(self::STATUS_CANCELLED);
    }

    public function normalizedSourceType(): ?string
    {
        $value = trim((string) $this->source_type);

        return $value === '' ? null : strtolower($value);
    }

    public function sourceRecord(): Service|Purchase|Location|Warehouse|VendingRoute|null
    {
        if (! $this->source_id || ! $this->normalizedSourceType()) {
            return null;
        }

        $query = match ($this->normalizedSourceType()) {
            self::SOURCE_TYPE_SERVICE => Service::query(),
            self::SOURCE_TYPE_PURCHASE => Purchase::query(),
            self::SOURCE_TYPE_ROUTE => VendingRoute::query(),
            self::SOURCE_TYPE_LOCATION => Location::query(),
            self::SOURCE_TYPE_WAREHOUSE => Warehouse::query(),
            default => null,
        };

        return $query?->where('account_id', $this->account_id)->find($this->source_id);
    }

    public function sourceRouteName(): ?string
    {
        return match ($this->normalizedSourceType()) {
            self::SOURCE_TYPE_SERVICE => 'services.show',
            self::SOURCE_TYPE_PURCHASE => 'purchases.show',
            self::SOURCE_TYPE_ROUTE => 'routes.show',
            self::SOURCE_TYPE_LOCATION => 'locations.show',
            self::SOURCE_TYPE_WAREHOUSE => 'warehouses.show',
            default => null,
        };
    }

    public function sourceLinkLabel(): ?string
    {
        $record = $this->sourceRecord();

        if (! $record) {
            return null;
        }

        return match ($this->normalizedSourceType()) {
            self::SOURCE_TYPE_SERVICE => 'View Service',
            self::SOURCE_TYPE_PURCHASE => 'View Purchase',
            self::SOURCE_TYPE_ROUTE => 'View Route',
            self::SOURCE_TYPE_LOCATION => 'View Location',
            self::SOURCE_TYPE_WAREHOUSE => 'View Warehouse',
            default => null,
        };
    }

    public function getCalendarColorClassAttribute(): string
    {
        return match (strtolower(trim((string) $this->event_type))) {
            'service' => 'calendar-event--service',
            'maintenance' => 'calendar-event--maintenance',
            'purchase' => 'calendar-event--purchase',
            default => 'calendar-event--default',
        };
    }

    public static function supportedSourceTypes(): array
    {
        return [
            self::SOURCE_TYPE_SERVICE,
            self::SOURCE_TYPE_PURCHASE,
            self::SOURCE_TYPE_ROUTE,
            self::SOURCE_TYPE_LOCATION,
            self::SOURCE_TYPE_WAREHOUSE,
        ];
    }

    protected function statusMatches(string $expectedStatus): bool
    {
        return strcasecmp(trim((string) $this->status), $expectedStatus) === 0;
    }
}

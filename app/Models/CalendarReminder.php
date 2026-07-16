<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class CalendarReminder extends Model
{
    use BelongsToAccount;

    public const STATUS_PENDING = 'Pending';
    public const STATUS_DISMISSED = 'Dismissed';
    public const TYPE_DASHBOARD = 'dashboard';

    protected $table = 'tbl_calendar_reminders';

    protected $fillable = [
        'account_id',
        'calendar_event_id',
        'remind_at',
        'reminder_type',
        'status',
        'assigned_user_id',
        'message',
        'dismissed_at',
        'dismissed_by_user_id',
    ];

    protected $casts = [
        'remind_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(CalendarEvent::class, 'calendar_event_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function dismissedBy()
    {
        return $this->belongsTo(User::class, 'dismissed_by_user_id');
    }

    public function scopeDue($query)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('remind_at', '<=', now());
    }
}

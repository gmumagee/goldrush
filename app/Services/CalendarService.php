<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\CalendarReminder;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CalendarService
{
    public const REMINDER_OPTION_NONE = 'none';
    public const REMINDER_OPTION_AT_EVENT_TIME = 'at_event_time';
    public const REMINDER_OPTION_15_MINUTES = '15_minutes_before';
    public const REMINDER_OPTION_1_HOUR = '1_hour_before';
    public const REMINDER_OPTION_1_DAY = '1_day_before';
    public const REMINDER_OPTION_1_WEEK = '1_week_before';
    public const REMINDER_OPTION_CUSTOM = 'custom';

    public function createEvent(array $data): CalendarEvent
    {
        return CalendarEvent::create($this->normalizeEventData($data));
    }

    public function updateEvent(CalendarEvent $event, array $data): CalendarEvent
    {
        $event->fill($this->normalizeEventData($data));
        $event->save();

        if ($event->isCompleted() || $event->isCancelled()) {
            $this->dismissPendingReminders($event, $data['dismissed_by_user_id'] ?? null);
        }

        return $event->refresh();
    }

    public function syncReminder(
        CalendarEvent $event,
        ?string $reminderOption,
        ?CarbonImmutable $customDateTime = null,
        ?string $message = null
    ): ?CalendarReminder {
        $reminderOption = $reminderOption ?: self::REMINDER_OPTION_NONE;
        $remindAt = $this->remindAtForOption($event, $reminderOption, $customDateTime);

        $pendingQuery = $event->reminders()
            ->where('reminder_type', CalendarReminder::TYPE_DASHBOARD)
            ->where('status', CalendarReminder::STATUS_PENDING)
            ->orderBy('id');

        if (! $event->isScheduled() || ! $remindAt) {
            $pendingQuery->delete();

            return null;
        }

        $reminder = $pendingQuery->first();

        if (! $reminder) {
            $reminder = new CalendarReminder([
                'account_id' => $event->account_id,
                'calendar_event_id' => $event->id,
            ]);
        }

        $reminder->fill([
            'account_id' => $event->account_id,
            'calendar_event_id' => $event->id,
            'remind_at' => $remindAt,
            'reminder_type' => CalendarReminder::TYPE_DASHBOARD,
            'status' => CalendarReminder::STATUS_PENDING,
            'assigned_user_id' => $event->assigned_user_id,
            'message' => $message,
            'dismissed_at' => null,
            'dismissed_by_user_id' => null,
        ]);
        $reminder->save();

        $pendingQuery
            ->where('id', '!=', $reminder->id)
            ->delete();

        return $reminder->refresh();
    }

    public function createServiceEvent(Service $service, ?int $createdByUserId = null): CalendarEvent
    {
        return $this->persistServiceEvent($service, $createdByUserId);
    }

    public function updateServiceEvent(Service $service): void
    {
        $this->persistServiceEvent($service, null);
    }

    public function deleteServiceEvent(Service $service): void
    {
        CalendarEvent::query()
            ->forAccount($service->account_id)
            ->where('source_type', CalendarEvent::SOURCE_TYPE_SERVICE)
            ->where('source_id', $service->id)
            ->delete();
    }

    public function dismissReminder(CalendarReminder $reminder, ?int $dismissedByUserId = null): void
    {
        $reminder->update([
            'status' => CalendarReminder::STATUS_DISMISSED,
            'dismissed_at' => now(),
            'dismissed_by_user_id' => $dismissedByUserId,
        ]);
    }

    public function completeEvent(CalendarEvent $event, ?int $dismissedByUserId = null): void
    {
        $event->update([
            'status' => CalendarEvent::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $this->dismissPendingReminders($event, $dismissedByUserId);
    }

    public function cancelEvent(CalendarEvent $event, ?int $dismissedByUserId = null): void
    {
        $event->update([
            'status' => CalendarEvent::STATUS_CANCELLED,
            'completed_at' => null,
        ]);

        $this->dismissPendingReminders($event, $dismissedByUserId);
    }

    public function getDueDashboardReminders(int $accountId): Collection
    {
        return CalendarReminder::query()
            ->forAccount($accountId)
            ->due()
            ->where('reminder_type', CalendarReminder::TYPE_DASHBOARD)
            ->with([
                'assignedUser',
                'event.assignedUser',
                'event.location',
                'event.warehouse',
                'event.route',
            ])
            ->orderBy('remind_at')
            ->orderBy('id')
            ->get();
    }

    public function getUpcomingEvents(int $accountId, int $days = 7): Collection
    {
        return CalendarEvent::query()
            ->forAccount($accountId)
            ->where('status', CalendarEvent::STATUS_SCHEDULED)
            ->whereBetween('start_at', [now(), now()->copy()->addDays($days)])
            ->with(['assignedUser', 'location', 'warehouse', 'route'])
            ->orderBy('start_at')
            ->orderBy('id')
            ->get();
    }

    public function reminderOptions(): array
    {
        return [
            self::REMINDER_OPTION_NONE,
            self::REMINDER_OPTION_AT_EVENT_TIME,
            self::REMINDER_OPTION_15_MINUTES,
            self::REMINDER_OPTION_1_HOUR,
            self::REMINDER_OPTION_1_DAY,
            self::REMINDER_OPTION_1_WEEK,
            self::REMINDER_OPTION_CUSTOM,
        ];
    }

    protected function normalizeEventData(array $data): array
    {
        $status = $data['status'] ?? CalendarEvent::STATUS_SCHEDULED;

        $data['all_day'] = (bool) ($data['all_day'] ?? false);
        $data['source_type'] = isset($data['source_type']) && trim((string) $data['source_type']) !== ''
            ? strtolower(trim((string) $data['source_type']))
            : null;
        $data['source_id'] = $data['source_id'] ?? null;
        $data['completed_at'] = strcasecmp((string) $status, CalendarEvent::STATUS_COMPLETED) === 0
            ? ($data['completed_at'] ?? now())
            : null;

        return $data;
    }

    protected function dismissPendingReminders(CalendarEvent $event, ?int $dismissedByUserId = null): void
    {
        CalendarReminder::query()
            ->forAccount($event->account_id)
            ->where('calendar_event_id', $event->id)
            ->where('status', CalendarReminder::STATUS_PENDING)
            ->update([
                'status' => CalendarReminder::STATUS_DISMISSED,
                'dismissed_at' => now(),
                'dismissed_by_user_id' => $dismissedByUserId,
                'updated_at' => now(),
            ]);
    }

    protected function remindAtForOption(
        CalendarEvent $event,
        string $reminderOption,
        ?CarbonImmutable $customDateTime = null
    ): ?CarbonImmutable {
        $startAt = $event->start_at ? CarbonImmutable::instance($event->start_at) : null;

        if (! $startAt) {
            return null;
        }

        return match ($reminderOption) {
            self::REMINDER_OPTION_AT_EVENT_TIME => $startAt,
            self::REMINDER_OPTION_15_MINUTES => $startAt->subMinutes(15),
            self::REMINDER_OPTION_1_HOUR => $startAt->subHour(),
            self::REMINDER_OPTION_1_DAY => $startAt->subDay(),
            self::REMINDER_OPTION_1_WEEK => $startAt->subWeek(),
            self::REMINDER_OPTION_CUSTOM => $customDateTime,
            default => null,
        };
    }

    protected function persistServiceEvent(Service $service, ?int $createdByUserId = null): CalendarEvent
    {
        $service->loadMissing(['location.route']);

        $event = CalendarEvent::query()
            ->forAccount($service->account_id)
            ->where('source_type', CalendarEvent::SOURCE_TYPE_SERVICE)
            ->where('source_id', $service->id)
            ->first();

        $data = [
            'account_id' => $service->account_id,
            'event_type' => 'Service',
            'title' => 'Service: '.($service->location?->location_name ?? 'Unknown Location'),
            'description' => null,
            'start_at' => $service->scheduled_at ?? $service->service_date,
            'end_at' => null,
            'all_day' => false,
            'status' => $service->isServiceCompleted() || $service->isServiceClosed()
                ? CalendarEvent::STATUS_COMPLETED
                : CalendarEvent::STATUS_SCHEDULED,
            'priority' => null,
            'assigned_user_id' => $service->user_id,
            'location_id' => $service->location_id,
            'warehouse_id' => $service->warehouse_id,
            'route_id' => $service->location?->route_id,
            'source_type' => CalendarEvent::SOURCE_TYPE_SERVICE,
            'source_id' => $service->id,
            'created_by_user_id' => $event?->created_by_user_id ?? $createdByUserId,
            'completed_at' => $service->isServiceCompleted() || $service->isServiceClosed()
                ? ($service->completed_at ?? $service->closed_at ?? now())
                : null,
        ];

        if ($event) {
            $this->updateEvent($event, $data);

            return $event->refresh();
        }

        return $this->createEvent($data);
    }
}

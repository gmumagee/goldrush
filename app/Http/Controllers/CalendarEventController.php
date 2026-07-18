<?php

namespace App\Http\Controllers;

use App\Models\AccountUser;
use App\Models\CalendarEvent;
use App\Models\CalendarReminder;
use App\Models\DataDictionary;
use App\Models\Location;
use App\Models\Purchase;
use App\Models\Service;
use App\Models\User;
use App\Models\VendingRoute;
use App\Models\Warehouse;
use App\Services\CalendarService;
use App\Services\DataDictionaryService;
use App\Support\AppDateTime;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CalendarEventController extends Controller
{
    public function __construct(
        protected DataDictionaryService $dataDictionaryService,
        protected CalendarService $calendarService,
    ) {
    }

    public function index(Request $request): View
    {
        $accountId = $this->currentAccountId($request);
        $filters = $this->validateIndexFilters($request, $accountId);
        $selectedDate = $filters['date'] ?? CarbonImmutable::now();
        $weekStart = $selectedDate->startOfWeek(CarbonInterface::SUNDAY)->startOfDay();
        $weekEnd = $selectedDate->endOfWeek(CarbonInterface::SATURDAY)->endOfDay();

        $events = CalendarEvent::query()
            ->forAccount($accountId)
            ->with(['assignedUser', 'location', 'warehouse', 'route'])
            ->whereBetween('start_at', [$weekStart, $weekEnd])
            ->when($filters['event_type'] ?? null, fn ($query, $value) => $query->where('event_type', $value))
            ->when(($filters['status'] ?? CalendarEvent::STATUS_SCHEDULED) !== 'all', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['assigned_user_id'] ?? null, fn ($query, $value) => $query->where('assigned_user_id', $value))
            ->when($filters['location_id'] ?? null, fn ($query, $value) => $query->where('location_id', $value))
            ->when($filters['search'] ?? null, function ($query, $value) {
                $search = trim((string) $value);

                $query->where(function ($eventQuery) use ($search) {
                    $eventQuery
                        ->where('title', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%')
                        ->orWhereHas('assignedUser', fn ($userQuery) => $userQuery->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('location', fn ($locationQuery) => $locationQuery->where('location_name', 'like', '%'.$search.'%'))
                        ->orWhereHas('warehouse', fn ($warehouseQuery) => $warehouseQuery->where('warehouse_name', 'like', '%'.$search.'%'))
                        ->orWhereHas('route', fn ($routeQuery) => $routeQuery->where('route_name', 'like', '%'.$search.'%'));
                });
            })
            ->orderBy('start_at')
            ->orderBy('id')
            ->get();

        $eventsByDate = $events->groupBy(fn (CalendarEvent $event) => $event->start_at?->toDateString());
        $weekDays = collect();

        for ($date = $weekStart; $date->lte($weekEnd); $date = $date->addDay()) {
            $weekDays->push($date);
        }

        return view('calendar-events.index', [
            'events' => $events,
            'eventsByDate' => $eventsByDate,
            'filters' => $filters,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'weekDays' => $weekDays,
            'eventTypeLabels' => $this->dataDictionaryService->labels(DataDictionary::GROUP_CALENDAR_EVENT_TYPE, $accountId, true),
            'eventStatusLabels' => $this->dataDictionaryService->labels(DataDictionary::GROUP_CALENDAR_EVENT_STATUS, $accountId, true),
            'eventPriorityLabels' => $this->dataDictionaryService->labels(DataDictionary::GROUP_CALENDAR_EVENT_PRIORITY, $accountId, true),
            'eventTypeOptions' => $this->dataDictionaryService->options(DataDictionary::GROUP_CALENDAR_EVENT_TYPE, $accountId),
            'eventStatusOptions' => $this->dataDictionaryService->options(DataDictionary::GROUP_CALENDAR_EVENT_STATUS, $accountId),
            'users' => $this->assignableUsersForAccount($accountId)->get(),
            'locations' => $this->locationsForAccount($accountId),
        ]);
    }

    public function create(Request $request): View
    {
        $accountId = $this->currentAccountId($request);
        [$sourceType, $sourceRecord, $sourceDefaults] = $this->sourceContextFromRequest($request, $accountId);

        $calendarEvent = new CalendarEvent($sourceDefaults + [
            'status' => CalendarEvent::STATUS_SCHEDULED,
            'all_day' => false,
            'start_at' => now(),
        ]);

        return view('calendar-events.create', $this->formViewData(
            $accountId,
            $calendarEvent,
            $sourceType,
            $sourceRecord,
            CalendarService::REMINDER_OPTION_NONE,
            null,
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $accountId = $this->currentAccountId($request);
        $payload = $this->validateEvent($request, $accountId);

        $event = DB::transaction(function () use ($payload, $request, $accountId) {
            $event = $this->calendarService->createEvent($payload['event'] + [
                'account_id' => $accountId,
                'created_by_user_id' => (int) $request->user()->id,
            ]);

            $this->calendarService->syncReminder(
                $event,
                $payload['reminder_option'],
                $payload['custom_reminder_at'],
            );

            return $event;
        });

        return redirect()
            ->route('calendar-events.show', $event)
            ->with('status', 'Calendar event created successfully.');
    }

    public function show(Request $request, int $calendar_event): View
    {
        $event = $this->resolveCalendarEvent($request, $calendar_event, [
            'assignedUser',
            'createdBy',
            'location',
            'warehouse',
            'route',
            'reminders.assignedUser',
            'reminders.dismissedBy',
        ]);

        return view('calendar-events.show', [
            'event' => $event,
            'eventTypeLabels' => $this->dataDictionaryService->labels(DataDictionary::GROUP_CALENDAR_EVENT_TYPE, $event->account_id, true),
            'eventStatusLabels' => $this->dataDictionaryService->labels(DataDictionary::GROUP_CALENDAR_EVENT_STATUS, $event->account_id, true),
            'eventPriorityLabels' => $this->dataDictionaryService->labels(DataDictionary::GROUP_CALENDAR_EVENT_PRIORITY, $event->account_id, true),
            'reminderStatusLabels' => $this->dataDictionaryService->labels(DataDictionary::GROUP_CALENDAR_REMINDER_STATUS, $event->account_id, true),
        ]);
    }

    public function edit(Request $request, int $calendar_event): View
    {
        $event = $this->resolveCalendarEvent($request, $calendar_event, [
            'location.route',
            'reminders',
        ]);

        [$reminderOption, $customReminderAt] = $this->reminderFormState($event);

        return view('calendar-events.edit', $this->formViewData(
            $event->account_id,
            $event,
            $event->source_type,
            $event->sourceRecord(),
            $reminderOption,
            $customReminderAt,
        ));
    }

    public function update(Request $request, int $calendar_event): RedirectResponse
    {
        $event = $this->resolveCalendarEvent($request, $calendar_event);
        $payload = $this->validateEvent($request, $event->account_id);

        DB::transaction(function () use ($event, $payload, $request) {
            $updatedEvent = $this->calendarService->updateEvent($event, $payload['event'] + [
                'dismissed_by_user_id' => (int) $request->user()->id,
            ]);

            $this->calendarService->syncReminder(
                $updatedEvent,
                $payload['reminder_option'],
                $payload['custom_reminder_at'],
            );
        });

        return redirect()
            ->route('calendar-events.show', $event)
            ->with('status', 'Calendar event updated successfully.');
    }

    public function destroy(Request $request, int $calendar_event): RedirectResponse
    {
        $event = $this->resolveCalendarEvent($request, $calendar_event);
        $event->delete();

        return redirect()
            ->route('calendar-events.index')
            ->with('status', 'Calendar event deleted successfully.');
    }

    public function complete(Request $request, int $calendarEvent): RedirectResponse
    {
        $event = $this->resolveCalendarEvent($request, $calendarEvent);
        $this->calendarService->completeEvent($event, (int) $request->user()->id);

        return redirect()
            ->route('calendar-events.show', $event)
            ->with('status', 'Calendar event completed.');
    }

    public function cancel(Request $request, int $calendarEvent): RedirectResponse
    {
        $event = $this->resolveCalendarEvent($request, $calendarEvent);
        $this->calendarService->cancelEvent($event, (int) $request->user()->id);

        return redirect()
            ->route('calendar-events.show', $event)
            ->with('status', 'Calendar event cancelled.');
    }

    protected function validateEvent(Request $request, int $accountId): array
    {
        $allDay = $request->boolean('all_day');

        $data = $request->validate([
            'event_type' => ['required', 'string', $this->activeDictionaryValueRule(DataDictionary::GROUP_CALENDAR_EVENT_TYPE, $accountId)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date' => ['required', 'regex:/^\d{2}-\d{2}-\d{4}$/'],
            'start_time' => [$allDay ? 'nullable' : 'required', 'regex:/^\d{2}:\d{2}:\d{2}$/'],
            'end_date' => ['nullable', 'regex:/^\d{2}-\d{2}-\d{4}$/'],
            'end_time' => ['nullable', 'regex:/^\d{2}:\d{2}:\d{2}$/'],
            'all_day' => ['nullable', 'boolean'],
            'status' => ['required', 'string', $this->activeDictionaryValueRule(DataDictionary::GROUP_CALENDAR_EVENT_STATUS, $accountId)],
            'priority' => ['nullable', 'string', $this->activeDictionaryValueRule(DataDictionary::GROUP_CALENDAR_EVENT_PRIORITY, $accountId)],
            'assigned_user_id' => ['nullable', 'integer'],
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('tbl_locations', 'id')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'warehouse_id' => [
                'nullable',
                'integer',
                Rule::exists('tbl_warehouses', 'id')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'route_id' => [
                'nullable',
                'integer',
                Rule::exists('tbl_routes', 'id')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'source_type' => ['nullable', 'string', Rule::in(CalendarEvent::supportedSourceTypes())],
            'source_id' => ['nullable', 'integer', 'required_with:source_type'],
            'reminder_option' => ['nullable', Rule::in($this->calendarService->reminderOptions())],
            'reminder_custom_date' => ['nullable', 'regex:/^\d{2}-\d{2}-\d{4}$/'],
            'reminder_custom_time' => ['nullable', 'regex:/^\d{2}:\d{2}:\d{2}$/'],
        ]);

        if (($data['assigned_user_id'] ?? null) !== null) {
            $this->ensureUserBelongsToAccount($accountId, (int) $data['assigned_user_id']);
        }

        $sourceType = isset($data['source_type']) && trim((string) $data['source_type']) !== ''
            ? strtolower(trim((string) $data['source_type']))
            : null;
        $sourceId = isset($data['source_id']) ? (int) $data['source_id'] : null;
        $sourceRecord = $this->resolveSourceRecordForAccount($accountId, $sourceType, $sourceId);

        if ($sourceType && ! $sourceRecord) {
            throw ValidationException::withMessages([
                'source_id' => 'The selected related record is not available for this account.',
            ]);
        }

        $sourceDefaults = $this->sourceDefaults($sourceType, $sourceRecord);
        $startAt = $this->normalizeDateTimeInput(
            $data['start_date'],
            $allDay ? ($data['start_time'] ?? '00:00:00') : ($data['start_time'] ?? null),
            'start_date',
            'start_time',
        );

        $endAt = $this->normalizeOptionalDateTimeInput(
            $data['end_date'] ?? null,
            $data['end_time'] ?? null,
            $allDay,
            'end_date',
            'end_time',
        );

        if ($endAt && $endAt->lt($startAt)) {
            throw ValidationException::withMessages([
                'end_date' => 'The end date and time must be after or equal to the start date and time.',
            ]);
        }

        $reminderOption = $data['reminder_option'] ?? CalendarService::REMINDER_OPTION_NONE;
        $customReminderAt = null;

        if ($reminderOption === CalendarService::REMINDER_OPTION_CUSTOM) {
            if (empty($data['reminder_custom_date']) || empty($data['reminder_custom_time'])) {
                throw ValidationException::withMessages([
                    'reminder_custom_date' => 'Custom reminders require both a date and a time.',
                ]);
            }

            $customReminderAt = $this->normalizeDateTimeInput(
                $data['reminder_custom_date'],
                $data['reminder_custom_time'],
                'reminder_custom_date',
                'reminder_custom_time',
            );
        }

        return [
            'event' => [
                'event_type' => $data['event_type'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'all_day' => $allDay,
                'status' => $data['status'],
                'priority' => $data['priority'] ?? null,
                'assigned_user_id' => $data['assigned_user_id'] ?? $sourceDefaults['assigned_user_id'] ?? null,
                'location_id' => $data['location_id'] ?? $sourceDefaults['location_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'] ?? $sourceDefaults['warehouse_id'] ?? null,
                'route_id' => $data['route_id'] ?? $sourceDefaults['route_id'] ?? null,
                'source_type' => $sourceType,
                'source_id' => $sourceRecord?->id,
            ],
            'reminder_option' => $reminderOption,
            'custom_reminder_at' => $customReminderAt,
        ];
    }

    protected function validateIndexFilters(Request $request, int $accountId): array
    {
        $allowedEventTypes = $this->dataDictionaryService->values(DataDictionary::GROUP_CALENDAR_EVENT_TYPE, $accountId);
        $allowedStatuses = $this->dataDictionaryService->values(DataDictionary::GROUP_CALENDAR_EVENT_STATUS, $accountId);
        $validated = validator($request->query(), [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'event_type' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'assigned_user_id' => ['nullable', 'integer'],
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('tbl_locations', 'id')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'search' => ['nullable', 'string', 'max:255'],
        ])->validate();

        if (($validated['assigned_user_id'] ?? null) !== null) {
            $this->ensureUserBelongsToAccount($accountId, (int) $validated['assigned_user_id']);
        }

        $eventType = isset($validated['event_type']) ? trim((string) $validated['event_type']) : '';

        if ($eventType !== '' && ! in_array($eventType, $allowedEventTypes, true)) {
            throw ValidationException::withMessages([
                'event_type' => 'The selected event type is invalid.',
            ]);
        }

        $status = isset($validated['status']) ? trim((string) $validated['status']) : '';

        if ($status !== '' && $status !== 'all' && ! in_array($status, $allowedStatuses, true)) {
            throw ValidationException::withMessages([
                'status' => 'The selected status is invalid.',
            ]);
        }

        return [
            'date' => ! empty($validated['date'])
                ? CarbonImmutable::createFromFormat('Y-m-d', $validated['date'])
                : CarbonImmutable::now(),
            'event_type' => $eventType !== ''
                ? $eventType
                : null,
            'status' => $status !== ''
                ? $status
                : CalendarEvent::STATUS_SCHEDULED,
            'assigned_user_id' => $validated['assigned_user_id'] ?? null,
            'location_id' => $validated['location_id'] ?? null,
            'search' => isset($validated['search']) ? trim((string) $validated['search']) : null,
        ];
    }

    protected function formViewData(
        int $accountId,
        CalendarEvent $calendarEvent,
        ?string $sourceType,
        Service|Purchase|Location|Warehouse|VendingRoute|null $sourceRecord,
        string $selectedReminderOption,
        ?CarbonImmutable $customReminderAt,
    ): array {
        return [
            'calendarEvent' => $calendarEvent,
            'eventTypeOptions' => $this->dataDictionaryService->options(DataDictionary::GROUP_CALENDAR_EVENT_TYPE, $accountId),
            'eventStatusOptions' => $this->dataDictionaryService->options(DataDictionary::GROUP_CALENDAR_EVENT_STATUS, $accountId),
            'eventPriorityOptions' => $this->dataDictionaryService->options(DataDictionary::GROUP_CALENDAR_EVENT_PRIORITY, $accountId),
            'users' => $this->assignableUsersForAccount($accountId)->get(),
            'locations' => $this->locationsForAccount($accountId),
            'warehouses' => $this->warehousesForAccount($accountId),
            'routes' => $this->routesForAccount($accountId),
            'sourceType' => $sourceType,
            'sourceRecord' => $sourceRecord,
            'selectedReminderOption' => old('reminder_option', $selectedReminderOption),
            'customReminderAt' => $customReminderAt,
            'reminderOptions' => [
                CalendarService::REMINDER_OPTION_NONE => 'No reminder',
                CalendarService::REMINDER_OPTION_AT_EVENT_TIME => 'At event time',
                CalendarService::REMINDER_OPTION_15_MINUTES => '15 minutes before',
                CalendarService::REMINDER_OPTION_1_HOUR => '1 hour before',
                CalendarService::REMINDER_OPTION_1_DAY => '1 day before',
                CalendarService::REMINDER_OPTION_1_WEEK => '1 week before',
                CalendarService::REMINDER_OPTION_CUSTOM => 'Custom date/time',
            ],
        ];
    }

    protected function resolveCalendarEvent(Request $request, int $eventId, array $with = []): CalendarEvent
    {
        return CalendarEvent::query()
            ->forAccount($this->currentAccountId($request))
            ->with($with)
            ->findOrFail($eventId);
    }

    protected function assignableUsersForAccount(int $accountId)
    {
        return User::query()
            ->select('tbl_users.*')
            ->join('tbl_account_users', 'tbl_account_users.user_id', '=', 'tbl_users.id')
            ->where('tbl_account_users.account_id', $accountId)
            ->where('tbl_account_users.status', AccountUser::STATUS_ACTIVE)
            ->where('tbl_users.status', User::STATUS_ACTIVE)
            ->distinct()
            ->orderBy('tbl_users.name');
    }

    protected function ensureUserBelongsToAccount(int $accountId, int $userId): void
    {
        if (! $this->assignableUsersForAccount($accountId)->where('tbl_users.id', $userId)->exists()) {
            throw ValidationException::withMessages([
                'assigned_user_id' => 'The selected user is not available for this account.',
            ]);
        }
    }

    protected function locationsForAccount(int $accountId)
    {
        return Location::query()
            ->where('account_id', $accountId)
            ->with('route')
            ->orderBy('location_name')
            ->get();
    }

    protected function warehousesForAccount(int $accountId)
    {
        return Warehouse::query()
            ->where('account_id', $accountId)
            ->orderBy('warehouse_name')
            ->get();
    }

    protected function routesForAccount(int $accountId)
    {
        return VendingRoute::query()
            ->where('account_id', $accountId)
            ->orderBy('route_name')
            ->get();
    }

    protected function sourceContextFromRequest(Request $request, int $accountId): array
    {
        $sourceType = $request->query('source_type');
        $sourceId = $request->query('source_id');

        if (! $sourceType || ! $sourceId) {
            return [null, null, []];
        }

        $normalizedSourceType = strtolower(trim((string) $sourceType));
        abort_unless(in_array($normalizedSourceType, CalendarEvent::supportedSourceTypes(), true), 404);

        $sourceRecord = $this->resolveSourceRecordForAccount($accountId, $normalizedSourceType, (int) $sourceId);
        abort_unless($sourceRecord !== null, 404);

        return [$normalizedSourceType, $sourceRecord, $this->sourceDefaults($normalizedSourceType, $sourceRecord)];
    }

    protected function resolveSourceRecordForAccount(
        int $accountId,
        ?string $sourceType,
        ?int $sourceId
    ): Service|Purchase|Location|Warehouse|VendingRoute|null {
        if (! $sourceType || ! $sourceId) {
            return null;
        }

        return match ($sourceType) {
            CalendarEvent::SOURCE_TYPE_SERVICE => Service::query()
                ->where('account_id', $accountId)
                ->with('location.route')
                ->find($sourceId),
            CalendarEvent::SOURCE_TYPE_PURCHASE => Purchase::query()
                ->where('account_id', $accountId)
                ->with('warehouse')
                ->find($sourceId),
            CalendarEvent::SOURCE_TYPE_ROUTE => VendingRoute::query()
                ->where('account_id', $accountId)
                ->find($sourceId),
            CalendarEvent::SOURCE_TYPE_LOCATION => Location::query()
                ->where('account_id', $accountId)
                ->with('route')
                ->find($sourceId),
            CalendarEvent::SOURCE_TYPE_WAREHOUSE => Warehouse::query()
                ->where('account_id', $accountId)
                ->find($sourceId),
            default => null,
        };
    }

    protected function sourceDefaults(
        ?string $sourceType,
        Service|Purchase|Location|Warehouse|VendingRoute|null $sourceRecord
    ): array {
        if (! $sourceType || ! $sourceRecord) {
            return [];
        }

        return match ($sourceType) {
            CalendarEvent::SOURCE_TYPE_SERVICE => [
                'event_type' => 'Service',
                'title' => 'Service: '.($sourceRecord->location?->location_name ?? 'Unknown Location'),
                'assigned_user_id' => $sourceRecord->user_id,
                'location_id' => $sourceRecord->location_id,
                'warehouse_id' => $sourceRecord->warehouse_id,
                'route_id' => $sourceRecord->location?->route_id,
                'start_at' => CarbonImmutable::instance($sourceRecord->service_date)->startOfDay(),
                'end_at' => CarbonImmutable::instance($sourceRecord->service_date)->endOfDay(),
                'all_day' => true,
                'source_type' => $sourceType,
                'source_id' => $sourceRecord->id,
            ],
            CalendarEvent::SOURCE_TYPE_PURCHASE => [
                'event_type' => 'Purchase',
                'title' => 'Purchase: '.($sourceRecord->warehouse?->warehouse_name ?? 'Warehouse'),
                'warehouse_id' => $sourceRecord->warehouse_id,
                'source_type' => $sourceType,
                'source_id' => $sourceRecord->id,
            ],
            CalendarEvent::SOURCE_TYPE_ROUTE => [
                'event_type' => 'Route',
                'title' => 'Route: '.$sourceRecord->route_name,
                'route_id' => $sourceRecord->id,
                'source_type' => $sourceType,
                'source_id' => $sourceRecord->id,
            ],
            CalendarEvent::SOURCE_TYPE_LOCATION => [
                'event_type' => 'General',
                'title' => $sourceRecord->location_name,
                'location_id' => $sourceRecord->id,
                'route_id' => $sourceRecord->route_id,
                'source_type' => $sourceType,
                'source_id' => $sourceRecord->id,
            ],
            CalendarEvent::SOURCE_TYPE_WAREHOUSE => [
                'event_type' => 'Purchase',
                'title' => $sourceRecord->warehouse_name,
                'warehouse_id' => $sourceRecord->id,
                'source_type' => $sourceType,
                'source_id' => $sourceRecord->id,
            ],
            default => [],
        };
    }

    protected function reminderFormState(CalendarEvent $event): array
    {
        $reminder = $event->reminders
            ->first(fn (CalendarReminder $item) => $item->status === CalendarReminder::STATUS_PENDING
                && $item->reminder_type === CalendarReminder::TYPE_DASHBOARD);

        if (! $reminder || ! $event->start_at) {
            return [CalendarService::REMINDER_OPTION_NONE, null];
        }

        $startAt = CarbonImmutable::instance($event->start_at);
        $remindAt = CarbonImmutable::instance($reminder->remind_at);

        return match (true) {
            $remindAt->equalTo($startAt) => [CalendarService::REMINDER_OPTION_AT_EVENT_TIME, null],
            $remindAt->equalTo($startAt->subMinutes(15)) => [CalendarService::REMINDER_OPTION_15_MINUTES, null],
            $remindAt->equalTo($startAt->subHour()) => [CalendarService::REMINDER_OPTION_1_HOUR, null],
            $remindAt->equalTo($startAt->subDay()) => [CalendarService::REMINDER_OPTION_1_DAY, null],
            $remindAt->equalTo($startAt->subWeek()) => [CalendarService::REMINDER_OPTION_1_WEEK, null],
            default => [CalendarService::REMINDER_OPTION_CUSTOM, $remindAt],
        };
    }

    protected function normalizeDateTimeInput(
        ?string $dateValue,
        ?string $timeValue,
        string $dateField,
        string $timeField,
    ): CarbonImmutable {
        return $this->combineDateAndTimeInputs($dateValue, $timeValue, $dateField, $timeField);
    }

    protected function normalizeOptionalDateTimeInput(
        ?string $dateValue,
        ?string $timeValue,
        bool $allDay,
        string $dateField,
        string $timeField,
    ): ?CarbonImmutable {
        $dateValue = is_string($dateValue) ? trim($dateValue) : null;
        $timeValue = is_string($timeValue) ? trim($timeValue) : null;

        if ($dateValue === '' && $timeValue === '') {
            return null;
        }

        if (($dateValue === '' && $timeValue !== '') || ($dateValue !== '' && $timeValue === '' && ! $allDay)) {
            throw ValidationException::withMessages([
                $dateField => 'Both the date and time are required when setting an end date.',
            ]);
        }

        return $this->combineDateAndTimeInputs(
            $dateValue,
            $allDay ? ($timeValue ?: '23:59:59') : $timeValue,
            $dateField,
            $timeField,
        );
    }
}

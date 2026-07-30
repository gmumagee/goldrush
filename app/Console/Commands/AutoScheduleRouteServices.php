<?php

namespace App\Console\Commands;

use App\Models\AccountUser;
use App\Models\CalendarEvent;
use App\Models\Location;
use App\Models\Service;
use App\Models\User;
use App\Models\VendingRoute;
use App\Services\CalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AutoScheduleRouteServices extends Command
{
    protected const ROUTE_ISSUE_EVENT_TYPE = 'Route Schedule';
    protected const ROUTE_ISSUE_EVENT_TITLE_PREFIX = 'Route Schedule: ';

    protected $signature = 'services:auto-schedule-routes {--account_id=} {--date=}';

    protected $description = 'Create location services 7 days ahead for auto-scheduled routes.';

    public function handle(CalendarService $calendarService): int
    {
        try {
            $accountId = $this->resolveAccountIdOption();
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $today = $this->resolveToday();

        if ($today === null) {
            return self::FAILURE;
        }

        $targetDate = $today->addDays(7);
        $targetDayName = $targetDate->format('l');

        $routes = VendingRoute::query()
            ->where('scheduled_day', $targetDayName)
            ->where('auto_schedule_enabled', true)
            ->when($accountId !== null, fn ($query) => $query->where('account_id', $accountId))
            ->with([
                'routeLocations' => fn ($query) => $query->with('location')->orderBy('stop_order')->orderBy('id'),
                'warehouse',
                'assignedUser',
            ])
            ->orderBy('account_id')
            ->orderBy('route_name')
            ->get();

        $servicesCreated = 0;
        $servicesSkippedAsDuplicates = 0;
        $locationSkips = 0;
        $invalidUserWarnings = 0;
        $routesWithIssues = 0;

        $this->info(sprintf(
            'Auto-scheduling route services for %s %s',
            $targetDate->toDateString(),
            $targetDayName
        ));
        $this->line('Routes found: '.$routes->count());

        foreach ($routes as $route) {
            $routeIssues = [];
            $locationIssues = [];
            $resolvedWarehouseId = $this->resolveWarehouseIdForRoute($route);

            if ($route->warehouse_id !== null && $resolvedWarehouseId === null) {
                $routeIssues[] = 'Default warehouse is assigned outside the route account scope.';
                $this->warn(sprintf(
                    'Route #%d (%s) default warehouse is outside the route account scope. Services will be created without a warehouse.',
                    $route->id,
                    $route->route_name
                ));
            } elseif ($route->warehouse_id === null) {
                $routeIssues[] = 'No default warehouse is assigned.';
                $this->warn(sprintf(
                    'Route #%d (%s) has no default warehouse assigned. Services will be created without a warehouse.',
                    $route->id,
                    $route->route_name
                ));
            }

            $assignedUserId = $this->resolveAssignedUserIdForRoute($route);

            if ($route->assigned_user_id && $assignedUserId === null) {
                $invalidUserWarnings++;
                $routeIssues[] = 'Assigned technician is outside the account scope or inactive; scheduled work remains unassigned.';
                $this->warn(sprintf(
                    'Route #%d (%s) has an assigned technician outside the account scope. Services will be created unassigned.',
                    $route->id,
                    $route->route_name
                ));
            }

            foreach ($route->routeLocations as $routeLocation) {
                $location = $routeLocation->location;

                if (! $this->locationBelongsToRouteAccount($route, $location)) {
                    $locationSkips++;
                    $locationIssues[] = sprintf(
                        'Route stop #%d is missing a valid current-account location.',
                        $routeLocation->id
                    );
                    $this->warn(sprintf(
                        'Skipping route stop #%d on route #%d (%s): location is missing or belongs to another account.',
                        $routeLocation->id,
                        $route->id,
                        $route->route_name
                    ));

                    continue;
                }

                $existingService = $this->findExistingService($route->account_id, $location->id, $targetDate);

                if ($existingService) {
                    $servicesSkippedAsDuplicates++;
                    $this->ensureCalendarArtifacts(
                        $calendarService,
                        $existingService,
                        $route,
                        $location,
                        $targetDate,
                        $assignedUserId
                    );

                    continue;
                }

                DB::transaction(function () use (
                    $calendarService,
                    $route,
                    $location,
                    $targetDate,
                    $assignedUserId,
                    $resolvedWarehouseId,
                    &$servicesCreated
                ) {
                    $service = Service::create([
                        'account_id' => $route->account_id,
                        'location_id' => $location->id,
                        'warehouse_id' => $resolvedWarehouseId,
                        'user_id' => $assignedUserId,
                        'closed_by_user_id' => null,
                        'service_type' => Service::TYPE_LOCATION_SERVICE,
                        'service_date' => $targetDate->toDateString(),
                        'scheduled_at' => null,
                        'opened_at' => null,
                        'completed_at' => null,
                        'closed_at' => null,
                        'amount_collected' => null,
                        'status' => Service::STATUS_AWAITING_SERVICE,
                    ]);

                    $this->ensureCalendarArtifacts(
                        $calendarService,
                        $service,
                        $route,
                        $location,
                        $targetDate,
                        $assignedUserId
                    );

                    $servicesCreated++;
                });
            }

            $allIssues = [...$routeIssues, ...$locationIssues];

            if ($allIssues !== []) {
                $routesWithIssues++;
                $this->upsertRouteIssueEvent($calendarService, $route, $targetDate, $assignedUserId, $resolvedWarehouseId, $allIssues);
            } else {
                $this->deleteRouteIssueEvent($route, $targetDate);
            }
        }

        $this->line('Services created: '.$servicesCreated);
        $this->line('Services skipped as duplicates: '.$servicesSkippedAsDuplicates);
        $this->line('Routes with scheduling issues: '.$routesWithIssues);

        if ($locationSkips > 0) {
            $this->line('Route stops skipped for invalid locations: '.$locationSkips);
        }

        if ($invalidUserWarnings > 0) {
            $this->line('Routes with invalid assigned technicians: '.$invalidUserWarnings);
        }

        return self::SUCCESS;
    }

    protected function resolveToday(): ?CarbonImmutable
    {
        $timezone = (string) config('app.schedule_timezone', config('app.timezone', 'UTC'));
        $dateOption = $this->option('date');

        if (! $dateOption) {
            return CarbonImmutable::now($timezone)->startOfDay();
        }

        try {
            return CarbonImmutable::parse((string) $dateOption, $timezone)->startOfDay();
        } catch (\Throwable) {
            $this->error('The --date option must be a valid date string.');

            return null;
        }
    }

    protected function resolveAccountIdOption(): ?int
    {
        $accountIdOption = $this->option('account_id');

        if ($accountIdOption === null || $accountIdOption === '') {
            return null;
        }

        if (! is_numeric($accountIdOption) || (int) $accountIdOption < 1) {
            throw new \InvalidArgumentException('The --account_id option must be a positive integer.');
        }

        return (int) $accountIdOption;
    }

    protected function resolveWarehouseIdForRoute(VendingRoute $route): ?int
    {
        if (
            $route->warehouse_id !== null
            && $route->warehouse !== null
            && (int) $route->warehouse->account_id === (int) $route->account_id
        ) {
            return (int) $route->warehouse_id;
        }

        return null;
    }

    protected function locationBelongsToRouteAccount(VendingRoute $route, ?Location $location): bool
    {
        return $location !== null && (int) $location->account_id === (int) $route->account_id;
    }

    protected function resolveAssignedUserIdForRoute(VendingRoute $route): ?int
    {
        if (! $route->assigned_user_id || ! $route->assignedUser) {
            return null;
        }

        if (strcasecmp(trim((string) $route->assignedUser->status), User::STATUS_ACTIVE) !== 0) {
            return null;
        }

        $userBelongsToAccount = AccountUser::query()
            ->where('account_id', $route->account_id)
            ->where('user_id', $route->assigned_user_id)
            ->where('status', AccountUser::STATUS_ACTIVE)
            ->exists();

        if (! $userBelongsToAccount) {
            return null;
        }

        return (int) $route->assigned_user_id;
    }

    protected function findExistingService(int $accountId, int $locationId, CarbonImmutable $targetDate): ?Service
    {
        return Service::query()
            ->where('account_id', $accountId)
            ->where('location_id', $locationId)
            ->whereDate('service_date', $targetDate->toDateString())
            ->where('service_type', Service::TYPE_LOCATION_SERVICE)
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', '!=', Service::STATUS_SERVICE_CLOSED);
            })
            ->first();
    }

    protected function ensureCalendarArtifacts(
        CalendarService $calendarService,
        Service $service,
        VendingRoute $route,
        Location $location,
        CarbonImmutable $targetDate,
        ?int $assignedUserId
    ): void {
        $service->setRelation('location', $location);

        $event = $calendarService->createServiceEvent($service);

        $event->fill([
            'description' => 'Automatically scheduled from route: '.$route->route_name,
            'priority' => 'Normal',
            'route_id' => $route->id,
            'assigned_user_id' => $service->user_id,
            'location_id' => $service->location_id,
            'warehouse_id' => $service->warehouse_id,
            'start_at' => $targetDate->startOfDay(),
            'end_at' => $targetDate->endOfDay(),
            'all_day' => true,
        ]);
        $event->save();

        $calendarService->syncReminder(
            $event->refresh(),
            CalendarService::REMINDER_OPTION_CUSTOM,
            CarbonImmutable::now(),
            sprintf(
                'Service scheduled for %s on %s',
                $location->location_name,
                $targetDate->format('m-d-Y')
            )
        );
    }

    protected function upsertRouteIssueEvent(
        CalendarService $calendarService,
        VendingRoute $route,
        CarbonImmutable $targetDate,
        ?int $assignedUserId,
        ?int $warehouseId,
        array $issues
    ): void {
        $event = $this->findRouteIssueEvent($route, $targetDate);

        $description = implode("\n", array_merge(
            ['Automatically scheduled route follow-up.'],
            ['Issues requiring review:'],
            array_map(fn (string $issue) => '- '.$issue, $issues),
        ));

        $data = [
            'account_id' => $route->account_id,
            'event_type' => self::ROUTE_ISSUE_EVENT_TYPE,
            'title' => self::ROUTE_ISSUE_EVENT_TITLE_PREFIX.$route->route_name,
            'description' => $description,
            'start_at' => $targetDate->startOfDay(),
            'end_at' => $targetDate->endOfDay(),
            'all_day' => true,
            'status' => CalendarEvent::STATUS_SCHEDULED,
            'priority' => 'High',
            'assigned_user_id' => $assignedUserId,
            'location_id' => null,
            'warehouse_id' => $warehouseId,
            'route_id' => $route->id,
            'source_type' => CalendarEvent::SOURCE_TYPE_ROUTE,
            'source_id' => $route->id,
            'created_by_user_id' => $event?->created_by_user_id,
            'completed_at' => null,
        ];

        $event = $event
            ? $calendarService->updateEvent($event, $data)
            : $calendarService->createEvent($data);

        $calendarService->syncReminder(
            $event->refresh(),
            CalendarService::REMINDER_OPTION_CUSTOM,
            CarbonImmutable::now(),
            sprintf(
                'Route %s scheduled with configuration issues for %s',
                $route->route_name,
                $targetDate->format('m-d-Y')
            )
        );
    }

    protected function deleteRouteIssueEvent(VendingRoute $route, CarbonImmutable $targetDate): void
    {
        $event = $this->findRouteIssueEvent($route, $targetDate);

        if ($event) {
            $event->delete();
        }
    }

    protected function findRouteIssueEvent(VendingRoute $route, CarbonImmutable $targetDate): ?CalendarEvent
    {
        return CalendarEvent::query()
            ->where('account_id', $route->account_id)
            ->where('route_id', $route->id)
            ->where('source_type', CalendarEvent::SOURCE_TYPE_ROUTE)
            ->where('source_id', $route->id)
            ->where('event_type', self::ROUTE_ISSUE_EVENT_TYPE)
            ->where('title', self::ROUTE_ISSUE_EVENT_TITLE_PREFIX.$route->route_name)
            ->whereDate('start_at', $targetDate->toDateString())
            ->first();
    }
}

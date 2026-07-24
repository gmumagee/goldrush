<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Machine;
use App\Services\CalendarService;
use App\Support\AppDateTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LocationMachineController extends Controller
{
    public function __construct(protected CalendarService $calendarService)
    {
    }

    public function create(Request $request, int $location): View
    {
        $this->authorize('create', Machine::class);

        $accountId = $this->currentAccountId($request);
        $location = $this->locationForAccount($accountId, $location);
        $this->authorize('update', $location);
        abort_if($location->isInventory(), 403, 'Machines cannot be attached to the inventory location.');

        $inventoryLocation = Location::ensureInventoryLocationForAccount($accountId);

        return view('locations.machines.attach', [
            'location' => $location,
            'inventoryMachines' => $this->inventoryMachinesForAccount($accountId, $inventoryLocation->id),
            'inventoryLocation' => $inventoryLocation,
            'defaultInstallationDate' => AppDateTime::isoDate(CarbonImmutable::now((string) config('app.timezone', 'UTC'))),
        ]);
    }

    public function store(Request $request, int $location): RedirectResponse
    {
        $this->authorize('create', Machine::class);

        $accountId = $this->currentAccountId($request);
        $location = $this->locationForAccount($accountId, $location);
        $this->authorize('update', $location);
        $this->ensureDeployableTargetLocation($location);

        $inventoryLocation = Location::ensureInventoryLocationForAccount($accountId);
        $data = $request->validate(
            [
                'machine_ids' => ['required', 'array', 'min:1'],
                'machine_ids.*' => ['required', 'integer', 'distinct'],
                'installation_date' => ['required', 'date_format:Y-m-d'],
            ],
            [
                'machine_ids.required' => 'Select at least one machine to attach.',
                'machine_ids.min' => 'Select at least one machine to attach.',
            ],
        );

        $machineIds = collect($data['machine_ids'])
            ->map(fn ($machineId) => (int) $machineId)
            ->values();
        $installationDate = CarbonImmutable::createFromFormat('Y-m-d', (string) $data['installation_date'])->toDateString();
        $installationDateStart = CarbonImmutable::createFromFormat('Y-m-d', $installationDate)->startOfDay();

        DB::transaction(function () use ($accountId, $inventoryLocation, $location, $machineIds, $installationDate, $installationDateStart, $request) {
            $machines = Machine::query()
                ->where('account_id', $accountId)
                ->whereIn('id', $machineIds->all())
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Machine $machine) => (int) $machine->id);

            if ($machines->count() !== $machineIds->count()) {
                throw ValidationException::withMessages([
                    'machine_ids' => 'Every selected machine must belong to the current account and currently be in inventory.',
                ]);
            }

            foreach ($machineIds as $machineId) {
                $machine = $machines->get($machineId);

                if (! $machine || (int) $machine->location_id !== (int) $inventoryLocation->id) {
                    throw ValidationException::withMessages([
                        'machine_ids' => 'Every selected machine must belong to the current account and currently be in inventory.',
                    ]);
                }

                $this->authorize('update', $machine);
            }

            foreach ($machineIds as $machineId) {
                /** @var Machine $machine */
                $machine = $machines->get($machineId);

                $machine->update([
                    'location_id' => $location->id,
                    'installed_on' => $installationDate,
                ]);

                $this->calendarService->createMachineInstallationEvent(
                    $machine->refresh()->load(['location.primaryRouteLocation.route']),
                    $location,
                    $installationDateStart,
                    (int) $request->user()->id,
                );
            }
        });

        return redirect()
            ->route('locations.show', $location)
            ->with('status', sprintf(
                '%d %s attached to %s. Installation %s created.',
                $machineIds->count(),
                $machineIds->count() === 1 ? 'machine' : 'machines',
                $location->location_name,
                $machineIds->count() === 1 ? 'event was' : 'events were',
            ));
    }

    protected function locationForAccount(int $accountId, int $locationId): Location
    {
        return Location::query()
            ->where('account_id', $accountId)
            ->findOrFail($locationId);
    }

    protected function inventoryMachinesForAccount(int $accountId, int $inventoryLocationId): Collection
    {
        return Machine::query()
            ->where('account_id', $accountId)
            ->where('location_id', $inventoryLocationId)
            ->with([
                'location' => fn ($query) => $query->where('account_id', $accountId),
            ])
            ->orderByRaw("LOWER(TRIM(COALESCE(type, '')))")
            ->orderByRaw("LOWER(TRIM(COALESCE(model, '')))")
            ->orderByRaw("LOWER(TRIM(COALESCE(serial_number, '')))")
            ->orderBy('id')
            ->get();
    }

    protected function ensureDeployableTargetLocation(Location $location): void
    {
        if ($location->isInventory()) {
            throw ValidationException::withMessages([
                'location' => 'Machines cannot be attached to the inventory location.',
            ]);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Machine;
use App\Models\VendingRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MachineController extends Controller
{
    public function index(Request $request): View
    {
        $accountId = (int) $request->session()->get('current_account_id');

        $machines = Machine::query()
            ->where('account_id', $accountId)
            ->with('location')
            ->withCount('bins')
            ->orderBy('id', 'desc')
            ->get();

        return view('machines.index', [
            'machines' => $machines,
        ]);
    }

    public function show(Request $request, Machine $machine): View
    {
        $accountId = (int) $request->session()->get('current_account_id');
        abort_unless($machine->account_id === $accountId, 404);

        $machine->load([
            'location',
            'bins' => fn ($query) => $query
                ->with('product')
                ->withSum('transactions as current_inventory', 'quantity')
                ->orderBy('bin_code'),
        ]);

        return view('machines.show', [
            'machine' => $machine,
        ]);
    }

    public function create(Request $request): View
    {
        $accountId = (int) $request->session()->get('current_account_id');

        $locations = Location::query()
            ->where('account_id', $accountId)
            ->orderBy('location_name')
            ->get();

        return view('machines.create', [
            'locations' => $locations,
            'defaultLocationId' => $locations->first()?->id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $accountId = (int) $request->session()->get('current_account_id');

        $data = $request->validate([
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('tbl_locations', 'id')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'type' => ['required', 'string', 'max:100'],
            'serial_number' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('tbl_machines', 'serial_number')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'model' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:50'],
            'installed_on' => ['nullable', 'date'],
        ]);

        $data['account_id'] = $accountId;
        $data['location_id'] = $data['location_id'] ?? $this->ensureDefaultLocation($accountId)->id;

        Machine::create($data);

        return redirect()
            ->route('machines.index')
            ->with('status', 'Machine created successfully.');
    }

    protected function ensureDefaultLocation(int $accountId): Location
    {
        $existingLocation = Location::query()
            ->where('account_id', $accountId)
            ->orderBy('id')
            ->first();

        if ($existingLocation) {
            return $existingLocation;
        }

        $route = VendingRoute::query()->firstOrCreate(
            [
                'account_id' => $accountId,
                'route_name' => 'Default Route',
            ],
            [
                'description' => 'Auto-created default route for machine assignment.',
            ]
        );

        return Location::query()->firstOrCreate(
            [
                'account_id' => $accountId,
                'route_id' => $route->id,
                'location_name' => 'Default Location',
            ],
            [
                'address' => null,
                'city' => null,
                'state' => null,
                'zip_code' => null,
                'contact_name' => null,
                'contact_phone' => null,
                'contact_email' => null,
            ]
        );
    }
}

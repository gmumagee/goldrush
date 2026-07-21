<?php

namespace App\Http\Controllers;

use App\Models\Bin;
use App\Models\Machine;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MachineBinController extends Controller
{
    public function create(Request $request, Machine $machine): View
    {
        $accountId = (int) $request->session()->get('current_account_id');
        abort_unless($machine->account_id === $accountId, 404);
        $this->authorize('update', $machine);

        $machine->load(['location', 'bins.product']);

        return view('machines.bins.create', [
            'machine' => $machine,
            'nextRowLetter' => $this->nextRowLetter($machine->bins),
            'rows' => $this->groupRows($machine->bins),
        ]);
    }

    public function edit(Request $request, Machine $machine): View
    {
        $accountId = (int) $request->session()->get('current_account_id');
        abort_unless($machine->account_id === $accountId, 404);
        $this->authorize('update', $machine);

        $machine->load([
            'location',
            'bins' => fn ($query) => $query->with('product')->orderBy('bin_code'),
        ]);

        $products = Product::query()
            ->where('account_id', $accountId)
            ->orderBy('product_name')
            ->get();

        return view('machines.bins.edit', [
            'machine' => $machine,
            'products' => $products,
        ]);
    }

    public function store(Request $request, Machine $machine): RedirectResponse
    {
        $accountId = (int) $request->session()->get('current_account_id');
        abort_unless($machine->account_id === $accountId, 404);
        $this->authorize('update', $machine);

        $data = $request->validate([
            'row_letter' => ['required', 'string', 'max:5', 'regex:/^[A-Za-z]+$/'],
            'bin_count' => ['required', 'integer', 'min:1', 'max:50'],
            'capacities' => ['required', 'array', 'min:1'],
            'capacities.*' => ['required', 'integer', 'min:0'],
        ]);

        $rowLetter = strtoupper($data['row_letter']);
        $exists = Bin::query()
            ->where('machine_id', $machine->id)
            ->where('bin_code', 'like', $rowLetter.'%')
            ->exists();

        if ($exists) {
            return back()
                ->withErrors(['row_letter' => 'That row already exists for this machine.'])
                ->withInput();
        }

        $count = (int) $data['bin_count'];
        $capacities = array_values(array_map('intval', $data['capacities']));

        if (count($capacities) !== $count) {
            return back()
                ->withErrors(['capacities' => 'Enter a capacity for each bin in the row.'])
                ->withInput();
        }

        for ($i = 1; $i <= $count; $i++) {
            Bin::create([
                'account_id' => $accountId,
                'machine_id' => $machine->id,
                'product_id' => null,
                'bin_code' => $rowLetter.$i,
                'capacity' => $capacities[$i - 1],
            ]);
        }

        return redirect()
            ->route('machines.bins.create', $machine)
            ->with('status', 'Added row '.$rowLetter.' with '.$count.' bins.');
    }

    public function update(Request $request, Machine $machine): RedirectResponse
    {
        $accountId = (int) $request->session()->get('current_account_id');
        abort_unless($machine->account_id === $accountId, 404);
        $this->authorize('update', $machine);

        $machine->load([
            'bins' => fn ($query) => $query->orderBy('bin_code'),
        ]);

        if ($machine->bins->isEmpty()) {
            throw ValidationException::withMessages([
                'machine' => 'This machine does not have any bins to edit.',
            ]);
        }

        $rules = [
            'bins' => ['required', 'array'],
        ];

        $attributes = [];

        foreach ($machine->bins as $bin) {
            $rules['bins.'.$bin->id.'.bin_code'] = ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'];
            $rules['bins.'.$bin->id.'.product_id'] = [
                'nullable',
                'integer',
                Rule::exists('tbl_products', 'id')->where(fn ($query) => $query->where('account_id', $accountId)),
            ];
            $rules['bins.'.$bin->id.'.capacity'] = ['required', 'integer', 'min:0'];
            $rules['bins.'.$bin->id.'.price'] = ['nullable', 'numeric', 'min:0'];

            $attributes['bins.'.$bin->id.'.bin_code'] = $bin->bin_code.' code';
            $attributes['bins.'.$bin->id.'.product_id'] = $bin->bin_code.' product';
            $attributes['bins.'.$bin->id.'.capacity'] = $bin->bin_code.' capacity';
            $attributes['bins.'.$bin->id.'.price'] = $bin->bin_code.' price';
        }

        $validated = validator($request->all(), $rules, [], $attributes)->validate();
        $binPayload = $validated['bins'];

        $normalizedCodes = collect();

        foreach ($machine->bins as $bin) {
            $payload = $binPayload[$bin->id] ?? null;

            if (! is_array($payload)) {
                throw ValidationException::withMessages([
                    'bins' => 'Update data is required for every bin on this machine.',
                ]);
            }

            $normalizedCodes->push(strtoupper((string) $payload['bin_code']));
        }

        if ($normalizedCodes->count() !== $normalizedCodes->unique()->count()) {
            throw ValidationException::withMessages([
                'bins' => 'Bin codes must be unique within a machine.',
            ]);
        }

        DB::transaction(function () use ($machine, $binPayload): void {
            foreach ($machine->bins as $bin) {
                $payload = $binPayload[$bin->id];

                $bin->update([
                    'product_id' => $payload['product_id'] ?? null,
                    'bin_code' => strtoupper((string) $payload['bin_code']),
                    'capacity' => (int) $payload['capacity'],
                    'price' => $payload['price'] !== null && $payload['price'] !== ''
                        ? (float) $payload['price']
                        : null,
                ]);
            }
        });

        return redirect()
            ->route('machines.show', $machine->id)
            ->with('status', 'Bins updated successfully.');
    }

    protected function groupRows(Collection $bins): Collection
    {
        return $bins
            ->sortBy('bin_code')
            ->groupBy(function (Bin $bin) {
                if (preg_match('/^([A-Z]+)/', strtoupper($bin->bin_code), $matches)) {
                    return $matches[1];
                }

                return 'OTHER';
            })
            ->map(function (Collection $rowBins, string $row) {
                return [
                    'row' => $row,
                    'count' => $rowBins->count(),
                    'bins' => $rowBins->values(),
                ];
            })
            ->values();
    }

    protected function nextRowLetter(Collection $bins): string
    {
        $letters = $bins
            ->map(function (Bin $bin) {
                if (preg_match('/^([A-Z]+)/', strtoupper($bin->bin_code), $matches)) {
                    return $matches[1];
                }

                return null;
            })
            ->filter()
            ->unique()
            ->values();

        if ($letters->isEmpty()) {
            return 'A';
        }

        $max = $letters->map(fn (string $letter) => $this->letterToNumber($letter))->max();

        return $this->numberToLetter($max + 1);
    }

    protected function letterToNumber(string $letters): int
    {
        $letters = strtoupper($letters);
        $number = 0;

        for ($i = 0; $i < strlen($letters); $i++) {
            $number = $number * 26 + (ord($letters[$i]) - 64);
        }

        return $number;
    }

    protected function numberToLetter(int $number): string
    {
        $letters = '';

        while ($number > 0) {
            $number--;
            $letters = chr(($number % 26) + 65).$letters;
            $number = intdiv($number, 26);
        }

        return $letters ?: 'A';
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Bin;
use App\Models\Machine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class MachineBinController extends Controller
{
    public function create(Request $request, Machine $machine): View
    {
        $accountId = (int) $request->session()->get('current_account_id');
        abort_unless($machine->account_id === $accountId, 404);

        $machine->load(['location', 'bins.product']);

        return view('machines.bins.create', [
            'machine' => $machine,
            'nextRowLetter' => $this->nextRowLetter($machine->bins),
            'rows' => $this->groupRows($machine->bins),
        ]);
    }

    public function store(Request $request, Machine $machine): RedirectResponse
    {
        $accountId = (int) $request->session()->get('current_account_id');
        abort_unless($machine->account_id === $accountId, 404);

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

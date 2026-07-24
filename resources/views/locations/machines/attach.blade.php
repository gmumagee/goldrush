<x-app-layout title="Attach Machine">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-5xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Add Machine</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Attach an existing inventory machine to {{ $location->location_name }} and create an installation event.</p>
                </div>
                <a href="{{ route('locations.show', $location) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Back to Location</a>
            </div>

            <x-validation-errors />

            @if ($inventoryMachines->isEmpty())
                <section class="panel">
                    <div class="panel-body space-y-4">
                        <div class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700/60 dark:text-gray-400">
                            No machines are currently in {{ $inventoryLocation->location_name }} for this account.
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <a href="{{ route('machines.create') }}" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-500">Create Machine</a>
                            <a href="{{ route('machines.index', ['location_scope' => 'in_inventory']) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">View Inventory Machines</a>
                        </div>
                    </div>
                </section>
            @else
                <section class="panel">
                    <div class="panel-body">
                        <form method="POST" action="{{ route('locations.machines.attach.store', $location) }}" class="space-y-5">
                            @csrf

                            <div class="grid gap-5 md:grid-cols-2">
                                <div>
                                    <x-label for="installation_date" value="Installation Date" />
                                    <x-input id="installation_date" name="installation_date" type="date" :value="old('installation_date', $defaultInstallationDate)" required />
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                                    <thead class="bg-gray-50 dark:bg-gray-800/80">
                                        <tr>
                                            <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">
                                                <label class="inline-flex items-center gap-2">
                                                    <input id="select-all-machines" type="checkbox" class="rounded border-gray-300 text-violet-600 focus:ring-violet-500">
                                                    <span>Select</span>
                                                </label>
                                            </th>
                                            <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Type</th>
                                            <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Model</th>
                                            <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Serial Number</th>
                                            <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                                        @foreach ($inventoryMachines as $machine)
                                            <tr class="bg-white dark:bg-gray-800">
                                                <td class="px-5 py-4">
                                                    <input
                                                        type="checkbox"
                                                        name="machine_ids[]"
                                                        value="{{ $machine->id }}"
                                                        @checked(collect(old('machine_ids', []))->contains($machine->id))
                                                        class="inventory-machine-checkbox rounded border-gray-300 text-violet-600 focus:ring-violet-500"
                                                    >
                                                </td>
                                                <td class="px-5 py-4 font-medium text-gray-800 dark:text-gray-100">{{ $machine->type ?: 'Machine #'.$machine->id }}</td>
                                                <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $machine->model ?: '—' }}</td>
                                                <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $machine->serial_number ?: '—' }}</td>
                                                <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ trim((string) $machine->status) !== '' ? trim((string) $machine->status) : '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('locations.show', $location) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</a>
                                <x-button>Attach Machines</x-button>
                            </div>
                        </form>
                    </div>
                </section>
            @endif
        </div>
    </div>

    @if ($inventoryMachines->isNotEmpty())
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const selectAll = document.getElementById('select-all-machines');
                const machineCheckboxes = Array.from(document.querySelectorAll('.inventory-machine-checkbox'));

                if (!selectAll || machineCheckboxes.length === 0) {
                    return;
                }

                const syncHeaderCheckbox = () => {
                    const checkedCount = machineCheckboxes.filter((checkbox) => checkbox.checked).length;

                    selectAll.checked = checkedCount > 0 && checkedCount === machineCheckboxes.length;
                    selectAll.indeterminate = checkedCount > 0 && checkedCount < machineCheckboxes.length;
                };

                selectAll.addEventListener('change', function () {
                    machineCheckboxes.forEach((checkbox) => {
                        checkbox.checked = selectAll.checked;
                    });

                    syncHeaderCheckbox();
                });

                machineCheckboxes.forEach((checkbox) => {
                    checkbox.addEventListener('change', syncHeaderCheckbox);
                });

                syncHeaderCheckbox();
            });
        </script>
    @endif
</x-app-layout>

<x-app-layout title="Add Bins">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Add Bins</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        {{ $machine->type }} · {{ $machine->serial_number ?: 'No serial' }} · {{ $machine->location?->location_name ?? 'No location' }}
                    </p>
                </div>

                <a href="{{ route('machines.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                    Back to Machines
                </a>
            </div>

            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">
                    {{ session('status') }}
                </div>
            @endif

            @php
                $initialBinCount = max(1, min(50, (int) old('bin_count', 10)));
                $initialCapacities = old('capacities', array_fill(0, $initialBinCount, 0));
                $initialCapacities = array_map(static fn ($value) => (int) $value, array_values($initialCapacities));
            @endphp

            <div class="grid gap-6 xl:grid-cols-2">
                <section class="panel">
                    <div class="panel-header">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Add Row</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Create bins like A1, A2, A3 and set the capacity for each one.</p>
                        </div>
                    </div>
                    <div class="panel-body">
                        <form
                            method="POST"
                            action="{{ route('machines.bins.store', $machine) }}"
                            class="space-y-5"
                            x-data="{
                                rowLetter: @js(old('row_letter', $nextRowLetter)),
                                binCount: {{ $initialBinCount }},
                                capacities: @js($initialCapacities),
                                normalizeCapacities() {
                                    let count = parseInt(this.binCount, 10);
                                    if (Number.isNaN(count) || count < 1) count = 1;
                                    if (count > 50) count = 50;
                                    this.binCount = count;

                                    if (!Array.isArray(this.capacities)) {
                                        this.capacities = [];
                                    }

                                    while (this.capacities.length < count) {
                                        this.capacities.push(0);
                                    }

                                    if (this.capacities.length > count) {
                                        this.capacities = this.capacities.slice(0, count);
                                    }
                                },
                                cleanRowLetter() {
                                    this.rowLetter = (this.rowLetter || '').toUpperCase().replace(/[^A-Z]/g, '').slice(0, 5);
                                },
                                binCode(index) {
                                    const prefix = ((this.rowLetter || '').toUpperCase().replace(/[^A-Z]/g, '').slice(0, 5) || 'A');
                                    return `${prefix}${index + 1}`;
                                }
                            }"
                            x-init="cleanRowLetter(); normalizeCapacities(); $watch('binCount', () => normalizeCapacities())"
                        >
                            @csrf

                            <div class="grid gap-5 md:grid-cols-2">
                                <div>
                                    <x-label for="row_letter" value="Row Letter" />
                                    <x-input id="row_letter" name="row_letter" type="text" :value="old('row_letter', $nextRowLetter)" x-model="rowLetter" @input="cleanRowLetter()" required />
                                </div>

                                <div>
                                    <x-label for="bin_count" value="Number of Bins" />
                                    <x-input id="bin_count" name="bin_count" type="number" min="1" max="50" :value="old('bin_count', 10)" x-model.number="binCount" required />
                                </div>
                            </div>

                            <div class="rounded-2xl border border-gray-200 p-5 dark:border-gray-700/60">
                                <div class="mb-4">
                                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Bin Capacities</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Enter the capacity for each bin that will be created in this row.</p>
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <template x-for="(capacity, index) in capacities" :key="index">
                                        <div>
                                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200" :for="`capacity_${index}`" x-text="`Capacity for ${binCode(index)}`"></label>
                                            <input
                                                class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm transition placeholder:text-gray-400 focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 dark:placeholder:text-gray-500"
                                                type="number"
                                                min="0"
                                                :id="`capacity_${index}`"
                                                :name="`capacities[${index}]`"
                                                x-model.number="capacities[index]"
                                                required
                                            >
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('machines.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                    Cancel
                                </a>
                                <x-button>Add Row</x-button>
                            </div>
                        </form>

                        <x-validation-errors class="mt-6" />
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-header">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Existing Rows</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Current bin layout for this machine.</p>
                        </div>
                    </div>
                    <div class="panel-body space-y-4">
                        @forelse ($rows as $row)
                            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700/60">
                                <div class="flex items-center justify-between gap-4">
                                    <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">Row {{ $row['row'] }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $row['count'] }} bins</div>
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($row['bins'] as $bin)
                                        <div class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-700 dark:bg-gray-900/40 dark:text-gray-300">
                                            {{ $bin->bin_code }} · Capacity {{ $bin->capacity }}
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <div class="text-sm text-gray-500 dark:text-gray-400">No bins have been added to this machine yet.</div>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>

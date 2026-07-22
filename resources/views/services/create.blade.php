<x-app-layout title="Create Service">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-3xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Create Service</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Create a location service or maintenance service for the selected account.</p>
                </div>
                <a href="{{ route('services.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Back to Services</a>
            </div>

            <section class="panel">
                <div class="panel-body">
                    @if ($locations->isEmpty())
                        <div class="rounded-xl border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800 dark:border-yellow-900/60 dark:bg-yellow-500/10 dark:text-yellow-300">You need at least one location before creating a service visit.</div>
                    @endif

                    @if (empty($serviceTypes))
                        <div class="rounded-xl border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800 dark:border-yellow-900/60 dark:bg-yellow-500/10 dark:text-yellow-300">No active service types are configured. Add a service type in the Data Dictionary before creating a service.</div>
                    @endif

                    <form method="POST" action="{{ route('services.store') }}" class="space-y-5">
                        @csrf

                        <div>
                            <x-label for="location_id" value="Location" />
                            <select id="location_id" name="location_id" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" required>
                                <option value="">Select a location</option>
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}" @selected(old('location_id', $selectedLocationId) == $location->id)>{{ $location->location_name }}{{ $location->primaryRouteLocation?->route?->route_name ? ' · '.$location->primaryRouteLocation->route->route_name : '' }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div id="warehouse-field-group">
                                <x-label for="warehouse_id" value="Source Warehouse" />
                                <select id="warehouse_id" name="warehouse_id" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                                    <option value="">Select a warehouse</option>
                                    @foreach ($warehouses as $warehouse)
                                        <option value="{{ $warehouse->id }}" @selected(old('warehouse_id') == $warehouse->id)>{{ $warehouse->warehouse_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <x-label for="service_type" value="Service Type" />
                                <select id="service_type" name="service_type" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" required>
                                    <option value="">Select a service type</option>
                                    @foreach ($serviceTypes as $value => $label)
                                        <option value="{{ $value }}" @selected(old('service_type') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-label for="service_date" value="Service Date" />
                                <x-input id="service_date" name="service_date" type="date" :value="old('service_date', now()->toDateString())" required />
                            </div>
                            <div>
                                <x-label for="user_id" value="Assigned User" />
                                <select id="user_id" name="user_id" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                                    <option value="">Current session user · {{ $currentUser->name }}</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <x-label for="notes" value="Notes" />
                            <textarea id="notes" name="notes" rows="4" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">{{ old('notes') }}</textarea>
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('services.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</a>
                            <x-button :disabled="$locations->isEmpty() || empty($serviceTypes)">Create Service</x-button>
                        </div>
                    </form>

                    <x-validation-errors class="mt-6" />
                </div>
            </section>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const serviceType = document.getElementById('service_type');
            const warehouseGroup = document.getElementById('warehouse-field-group');
            const warehouseInput = document.getElementById('warehouse_id');

            if (!serviceType || !warehouseGroup || !warehouseInput) {
                return;
            }

            const updateServiceTypeFields = () => {
                const isLocationService = serviceType.value === '{{ \App\Models\Service::TYPE_LOCATION }}';

                warehouseGroup.classList.toggle('hidden', !isLocationService);
                warehouseInput.required = isLocationService;

                if (!isLocationService) {
                    warehouseInput.value = '';
                }
            };

            serviceType.addEventListener('change', updateServiceTypeFields);
            updateServiceTypeFields();
        });
    </script>
</x-app-layout>

<x-app-layout title="Edit Route">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-3xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div><h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Edit Route</h1><p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Update route details for the selected account.</p></div>
                <a href="{{ route('routes.show', $route) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Back to Route</a>
            </div>
            <section class="panel">
                <div class="panel-body">
                    <form method="POST" action="{{ route('routes.update', $route) }}" class="space-y-5">
                        @csrf
                        @method('PATCH')
                        <div>
                            <x-label for="route_name" value="Route Name" />
                            <x-input id="route_name" name="route_name" type="text" :value="old('route_name', $route->route_name)" required />
                        </div>
                        <div>
                            <x-label for="scheduled_day" value="Scheduled Day" />
                            <select id="scheduled_day" name="scheduled_day" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" required>
                                <option value="">Select a day</option>
                                @foreach ($scheduledDayOptions as $scheduledDay)
                                    <option value="{{ $scheduledDay->value }}" @selected(old('scheduled_day', $route->scheduled_day) === $scheduledDay->value)>{{ $scheduledDay->displayLabel() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <x-label for="warehouse_id" value="Default Warehouse" />
                                <select id="warehouse_id" name="warehouse_id" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                                    <option value="">Select a warehouse</option>
                                    @foreach ($warehouses as $warehouse)
                                        <option value="{{ $warehouse->id }}" @selected((string) old('warehouse_id', $route->warehouse_id) === (string) $warehouse->id)>{{ $warehouse->warehouse_name }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Required when auto-scheduling is enabled.</p>
                            </div>
                            <div>
                                <x-label for="assigned_user_id" value="Assigned Technician" />
                                <select id="assigned_user_id" name="assigned_user_id" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                                    <option value="">Unassigned</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}" @selected((string) old('assigned_user_id', $route->assigned_user_id) === (string) $user->id)>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900/60">
                            <input type="hidden" name="auto_schedule_enabled" value="0">
                            <label for="auto_schedule_enabled" class="flex items-start gap-3">
                                <input id="auto_schedule_enabled" name="auto_schedule_enabled" type="checkbox" value="1" @checked((string) old('auto_schedule_enabled', $route->auto_schedule_enabled ? '1' : '0') === '1') class="mt-1 rounded border-gray-300 text-violet-600 focus:ring-violet-500">
                                <span>
                                    <span class="block text-sm font-medium text-gray-800 dark:text-gray-100">Auto Schedule Services</span>
                                    <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">When enabled, the daily scheduler will create location services 7 days before this route’s scheduled day.</span>
                                </span>
                            </label>
                        </div>
                        <div>
                            <x-label for="description" value="Description" />
                            <textarea id="description" name="description" rows="4" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm transition placeholder:text-gray-400 focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 dark:placeholder:text-gray-500">{{ old('description', $route->description) }}</textarea>
                        </div>
                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('routes.show', $route) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</a>
                            <x-button>Save Route</x-button>
                        </div>
                    </form>
                    <x-validation-errors class="mt-6" />
                </div>
            </section>
        </div>
    </div>
</x-app-layout>

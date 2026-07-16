@php
    $isEdit = $calendarEvent->exists;
@endphp

@if ($sourceType && $sourceRecord)
    <div class="rounded-xl border border-violet-200 bg-violet-50 px-4 py-3 text-sm text-violet-800 dark:border-violet-500/30 dark:bg-violet-500/10 dark:text-violet-300">
        Related record:
        <span class="font-medium">
            {{ match ($sourceType) {
                \App\Models\CalendarEvent::SOURCE_TYPE_SERVICE => 'Service #'.$sourceRecord->id,
                \App\Models\CalendarEvent::SOURCE_TYPE_PURCHASE => 'Purchase #'.$sourceRecord->id,
                \App\Models\CalendarEvent::SOURCE_TYPE_ROUTE => $sourceRecord->route_name,
                \App\Models\CalendarEvent::SOURCE_TYPE_LOCATION => $sourceRecord->location_name,
                \App\Models\CalendarEvent::SOURCE_TYPE_WAREHOUSE => $sourceRecord->warehouse_name,
                default => 'Related record',
            } }}
        </span>
    </div>
@endif

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <x-label for="event_type" value="Event Type" />
        <select id="event_type" name="event_type" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" required>
            <option value="">Select an event type</option>
            @foreach ($eventTypeOptions as $option)
                <option value="{{ $option->value }}" @selected(old('event_type', $calendarEvent->event_type) == $option->value)>{{ $option->displayLabel() }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <x-label for="title" value="Title" />
        <x-input id="title" name="title" type="text" :value="old('title', $calendarEvent->title)" required />
    </div>
</div>

<div>
    <x-label for="description" value="Description" />
    <textarea id="description" name="description" rows="4" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">{{ old('description', $calendarEvent->description) }}</textarea>
</div>

<div x-data="{ allDay: {{ old('all_day', $calendarEvent->all_day ? 'true' : 'false') ? 'true' : 'false' }}, reminderOption: @js($selectedReminderOption) }" class="space-y-5">
    <div class="grid gap-5 md:grid-cols-2">
        <div>
            <x-label for="start_date" value="Start Date" />
            <x-input id="start_date" name="start_date" type="text" placeholder="DD-MM-YYYY" :value="old('start_date', \App\Support\AppDateTime::inputDate($calendarEvent->start_at ?: now()))" required />
        </div>
        <div>
            <x-label for="start_time" value="Start Time" />
            <x-input id="start_time" name="start_time" type="text" placeholder="HH:MM:SS" :value="old('start_time', \App\Support\AppDateTime::inputTime($calendarEvent->start_at ?: now()))" x-bind:readonly="allDay" />
        </div>
    </div>

    <div class="grid gap-5 md:grid-cols-2">
        <div>
            <x-label for="end_date" value="End Date" />
            <x-input id="end_date" name="end_date" type="text" placeholder="DD-MM-YYYY" :value="old('end_date', \App\Support\AppDateTime::inputDate($calendarEvent->end_at))" />
        </div>
        <div>
            <x-label for="end_time" value="End Time" />
            <x-input id="end_time" name="end_time" type="text" placeholder="HH:MM:SS" :value="old('end_time', \App\Support\AppDateTime::inputTime($calendarEvent->end_at))" x-bind:readonly="allDay" />
        </div>
    </div>

    <label class="inline-flex items-center gap-3 text-sm font-medium text-gray-700 dark:text-gray-200">
        <input type="checkbox" name="all_day" value="1" class="rounded border-gray-300 text-violet-600 shadow-sm focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800" x-model="allDay" @checked(old('all_day', $calendarEvent->all_day))>
        <span>All Day</span>
    </label>

    <div class="grid gap-5 md:grid-cols-2">
        <div>
            <x-label for="status" value="Status" />
            <select id="status" name="status" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" required>
                @foreach ($eventStatusOptions as $option)
                    <option value="{{ $option->value }}" @selected(old('status', $calendarEvent->status ?: \App\Models\CalendarEvent::STATUS_SCHEDULED) == $option->value)>{{ $option->displayLabel() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-label for="priority" value="Priority" />
            <select id="priority" name="priority" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                <option value="">No priority</option>
                @foreach ($eventPriorityOptions as $option)
                    <option value="{{ $option->value }}" @selected(old('priority', $calendarEvent->priority) == $option->value)>{{ $option->displayLabel() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid gap-5 md:grid-cols-2">
        <div>
            <x-label for="assigned_user_id" value="Assigned User" />
            <select id="assigned_user_id" name="assigned_user_id" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                <option value="">No assigned user</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected(old('assigned_user_id', $calendarEvent->assigned_user_id) == $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-label for="location_id" value="Location" />
            <select id="location_id" name="location_id" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                <option value="">No location</option>
                @foreach ($locations as $location)
                    <option value="{{ $location->id }}" @selected(old('location_id', $calendarEvent->location_id) == $location->id)>{{ $location->location_name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid gap-5 md:grid-cols-2">
        <div>
            <x-label for="warehouse_id" value="Warehouse" />
            <select id="warehouse_id" name="warehouse_id" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                <option value="">No warehouse</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected(old('warehouse_id', $calendarEvent->warehouse_id) == $warehouse->id)>{{ $warehouse->warehouse_name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-label for="route_id" value="Route" />
            <select id="route_id" name="route_id" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                <option value="">No route</option>
                @foreach ($routes as $route)
                    <option value="{{ $route->id }}" @selected(old('route_id', $calendarEvent->route_id) == $route->id)>{{ $route->route_name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div>
        <x-label for="reminder_option" value="Reminder" />
        <select id="reminder_option" name="reminder_option" x-model="reminderOption" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
            @foreach ($reminderOptions as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div x-show="reminderOption === '{{ \App\Services\CalendarService::REMINDER_OPTION_CUSTOM }}'" x-cloak class="grid gap-5 md:grid-cols-2">
        <div>
            <x-label for="reminder_custom_date" value="Custom Reminder Date" />
            <x-input id="reminder_custom_date" name="reminder_custom_date" type="text" placeholder="DD-MM-YYYY" :value="old('reminder_custom_date', \App\Support\AppDateTime::inputDate($customReminderAt))" />
        </div>
        <div>
            <x-label for="reminder_custom_time" value="Custom Reminder Time" />
            <x-input id="reminder_custom_time" name="reminder_custom_time" type="text" placeholder="HH:MM:SS" :value="old('reminder_custom_time', \App\Support\AppDateTime::inputTime($customReminderAt))" />
        </div>
    </div>
</div>

<input type="hidden" name="source_type" value="{{ old('source_type', $sourceType) }}">
<input type="hidden" name="source_id" value="{{ old('source_id', $sourceRecord?->id) }}">

<div class="flex items-center justify-end gap-3">
    <a href="{{ $isEdit ? route('calendar-events.show', $calendarEvent) : route('calendar-events.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</a>
    <x-button>{{ $isEdit ? 'Save Event' : 'Create Event' }}</x-button>
</div>

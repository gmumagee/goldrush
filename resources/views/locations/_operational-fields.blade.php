@php
    $selectedPattern = old('service_pattern_type', $servicePatternTypeDefault);
@endphp

<section class="space-y-5 border-t border-gray-200 pt-5 dark:border-gray-700/60">
    <div>
        <h2 class="text-base font-semibold text-gray-800 dark:text-gray-100">Service Pattern</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Informational only. Route scheduling remains route-driven.</p>
    </div>

    <div x-data="{ pattern: @js((string) $selectedPattern) }" class="space-y-5">
        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <x-label for="service_pattern_type" value="Pattern" />
                <select id="service_pattern_type" name="service_pattern_type" x-model="pattern" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                    <option value="">No pattern set</option>
                    <option value="weekly">Weekly</option>
                    <option value="biweekly">Every 2 Weeks</option>
                    <option value="custom">Custom</option>
                </select>
            </div>
            <div x-show="pattern === 'custom'" x-cloak>
                <x-label for="service_interval_days_custom" value="Custom Interval (Days)" />
                <x-input id="service_interval_days_custom" name="service_interval_days_custom" type="number" min="1" :value="old('service_interval_days_custom', $serviceIntervalCustomDefault)" x-bind:disabled="pattern !== 'custom'" />
            </div>
        </div>
    </div>
</section>

<section class="space-y-5 border-t border-gray-200 pt-5 dark:border-gray-700/60">
    <div>
        <h2 class="text-base font-semibold text-gray-800 dark:text-gray-100">Financial Settings</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Enter percentage values as percentages. They are stored internally as fractional rates for future calculations.</p>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        <div class="space-y-3">
            <div class="max-w-xs">
                <x-label for="sales_tax_rate_percent" value="Sales Tax Rate" />
                <div class="flex items-center gap-3">
                    <x-input id="sales_tax_rate_percent" name="sales_tax_rate_percent" type="number" min="0" max="100" step="0.01" :value="old('sales_tax_rate_percent', $salesTaxRatePercentDefault)" />
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">%</span>
                </div>
            </div>
        </div>

        <div class="space-y-3">
            <div class="max-w-xs">
                <x-label for="commission_rate_percent" value="Commission Rate" />
                <div class="flex items-center gap-3">
                    <x-input id="commission_rate_percent" name="commission_rate_percent" type="number" min="0" max="100" step="0.01" :value="old('commission_rate_percent', $commissionRatePercentDefault)" />
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">%</span>
                </div>
            </div>
            <label class="inline-flex items-start gap-3 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" name="commission_on_net" value="1" @checked(old('commission_on_net', $commissionOnNetDefault)) class="mt-0.5 rounded border-gray-300 text-violet-600 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-600 dark:bg-gray-800">
                <span>
                    <span class="font-medium text-gray-800 dark:text-gray-100">Calculate commission on net sales</span>
                    <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Leave unchecked to calculate on gross sales. The exact gross vs. net definition is for the future commission report.</span>
                </span>
            </label>
        </div>
    </div>
</section>

<section class="space-y-5 border-t border-gray-200 pt-5 dark:border-gray-700/60">
    <div>
        <h2 class="text-base font-semibold text-gray-800 dark:text-gray-100">Access Days and Hours</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Informational only. Closed days are omitted when the location is saved.</p>
    </div>

    <div class="grid gap-3 lg:grid-cols-2">
        @foreach ($accessHourDayLabels as $dayOfWeek => $label)
            @php
                $dayDefaults = $accessHourDefaults[$dayOfWeek] ?? ['is_open' => false, 'opens_at' => '', 'closes_at' => ''];
                $isOpen = (bool) old("access_hours.$dayOfWeek.is_open", $dayDefaults['is_open']);
            @endphp
            <div x-data="{ open: @js($isOpen) }" class="rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-700/60">
                <div class="grid gap-3 lg:grid-cols-[56px_88px_minmax(0,1fr)] lg:items-end">
                    <div class="font-medium text-gray-800 dark:text-gray-100 lg:pb-3">{{ $label }}</div>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 lg:pb-3">
                        <input type="checkbox" name="access_hours[{{ $dayOfWeek }}][is_open]" value="1" x-model="open" @checked($isOpen) class="rounded border-gray-300 text-violet-600 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-600 dark:bg-gray-800">
                        Open
                    </label>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <x-label for="access_hours_{{ $dayOfWeek }}_opens_at" value="Opens" />
                            <input
                                id="access_hours_{{ $dayOfWeek }}_opens_at"
                                name="access_hours[{{ $dayOfWeek }}][opens_at]"
                                type="time"
                                step="60"
                                value="{{ old("access_hours.$dayOfWeek.opens_at", $dayDefaults['opens_at']) }}"
                                x-bind:disabled="!open"
                                class="block min-w-[140px] w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm transition focus:border-violet-500 focus:ring-violet-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 dark:disabled:bg-gray-900/50 dark:disabled:text-gray-500"
                            >
                        </div>
                        <div>
                            <x-label for="access_hours_{{ $dayOfWeek }}_closes_at" value="Closes" />
                            <input
                                id="access_hours_{{ $dayOfWeek }}_closes_at"
                                name="access_hours[{{ $dayOfWeek }}][closes_at]"
                                type="time"
                                step="60"
                                value="{{ old("access_hours.$dayOfWeek.closes_at", $dayDefaults['closes_at']) }}"
                                x-bind:disabled="!open"
                                class="block min-w-[140px] w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm transition focus:border-violet-500 focus:ring-violet-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 dark:disabled:bg-gray-900/50 dark:disabled:text-gray-500"
                            >
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</section>

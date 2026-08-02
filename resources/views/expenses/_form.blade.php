@php
    $expenseCategory = isset($expense) ? $expense->category : '';
    $expenseAmount = isset($expense) ? number_format((float) $expense->amount, 2, '.', '') : '';
    $expenseDate = isset($expense) ? \App\Support\AppDateTime::isoDate($expense->expense_date) : '';
    $expenseLocationId = isset($expense) ? $expense->location_id : '';
    $expenseVendor = isset($expense) ? $expense->vendor : '';
    $expenseDescription = isset($expense) ? $expense->description : '';
@endphp

<div>
    <x-label for="category" value="Category" />
    <select id="category" name="category" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" required>
        <option value="">Select a category</option>
        @foreach ($categoryOptions as $value => $label)
            <option value="{{ $value }}" @selected(old('category', $expenseCategory) === $value)>{{ $label }}</option>
        @endforeach
    </select>
</div>

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <x-label for="amount" value="Amount" />
        <x-input id="amount" name="amount" type="number" min="0.01" step="0.01" :value="old('amount', $expenseAmount)" required />
    </div>
    <div>
        <x-label for="expense_date" value="Expense Date" />
        <x-input id="expense_date" name="expense_date" type="date" :value="old('expense_date', $expenseDate)" required />
    </div>
</div>

<div>
    <x-label for="location_id" value="Location" />
    <select id="location_id" name="location_id" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
        <option value="">General (no location)</option>
        @foreach ($locations as $location)
            <option value="{{ $location->id }}" @selected((string) old('location_id', $expenseLocationId) === (string) $location->id)>{{ $location->location_name }}</option>
        @endforeach
    </select>
</div>

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <x-label for="vendor" value="Vendor" />
        <x-input id="vendor" name="vendor" type="text" :value="old('vendor', $expenseVendor)" />
    </div>
    <div>
        <x-label for="description" value="Description" />
        <textarea id="description" name="description" rows="3" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">{{ old('description', $expenseDescription) }}</textarea>
    </div>
</div>

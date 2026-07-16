<?php

use App\Models\DataDictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $timestamp = now();

        foreach ($this->dictionaryGroups() as $group => $values) {
            foreach (array_values($values) as $index => $entry) {
                $value = is_array($entry) ? $entry['value'] : $entry;
                $label = is_array($entry) ? ($entry['label'] ?? $value) : $entry;

                DB::table('tbl_data_dictionary')->updateOrInsert(
                    [
                        'account_id' => null,
                        'name' => $group,
                        'value' => $value,
                    ],
                    [
                        'label' => $label,
                        'sort_order' => ($index + 1) * 10,
                        'is_active' => true,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        DB::table('tbl_data_dictionary')
            ->whereNull('account_id')
            ->whereIn('name', array_keys($this->dictionaryGroups()))
            ->delete();
    }

    protected function dictionaryGroups(): array
    {
        return [
            DataDictionary::GROUP_CALENDAR_EVENT_TYPE => [
                'Service',
                'Purchase',
                'Route',
                'Maintenance',
                'Contract Renewal',
                'Insurance Renewal',
                'General',
            ],
            DataDictionary::GROUP_CALENDAR_EVENT_STATUS => [
                'Scheduled',
                'Completed',
                'Cancelled',
            ],
            DataDictionary::GROUP_CALENDAR_EVENT_PRIORITY => [
                'Low',
                'Normal',
                'High',
                'Urgent',
            ],
            DataDictionary::GROUP_CALENDAR_REMINDER_TYPE => [
                ['value' => 'dashboard', 'label' => 'dashboard'],
            ],
            DataDictionary::GROUP_CALENDAR_REMINDER_STATUS => [
                'Pending',
                'Dismissed',
            ],
        ];
    }
};

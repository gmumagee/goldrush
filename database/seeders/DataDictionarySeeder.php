<?php

namespace Database\Seeders;

use App\Models\DataDictionary;
use App\Models\Service;
use Illuminate\Database\Seeder;

class DataDictionarySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->dictionaryGroups() as $group => $values) {
            $this->seedGroup($group, $values);
        }
    }

    protected function dictionaryGroups(): array
    {
        return [
            DataDictionary::GROUP_SERVICE_STATUS => [
                'Awaiting Service',
                'Service Open',
                'Service Completed',
                'Service Closed',
            ],
            DataDictionary::GROUP_PURCHASE_STATUS => [
                'Posted',
                'Voided',
            ],
            DataDictionary::GROUP_MACHINE_STATUS => [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'inactive', 'label' => 'Inactive'],
                ['value' => 'repair', 'label' => 'Repair'],
                ['value' => 'retired', 'label' => 'Retired'],
            ],
            DataDictionary::GROUP_ACCOUNT_STATUS => [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'inactive', 'label' => 'Inactive'],
            ],
            DataDictionary::GROUP_USER_STATUS => [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'inactive', 'label' => 'Inactive'],
            ],
            DataDictionary::GROUP_ACCOUNT_USER_STATUS => [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'inactive', 'label' => 'Inactive'],
            ],
            DataDictionary::GROUP_ACCOUNT_USER_ROLE => [
                'Owner',
                'Admin',
                'Manager',
                'Technician',
                'Viewer',
            ],
            DataDictionary::GROUP_INVENTORY_MOVEMENT_TYPE => [
                ['value' => 'purchase', 'label' => 'Purchase'],
                ['value' => 'purchase_void', 'label' => 'Purchase Void'],
                ['value' => 'service_fill', 'label' => 'Service Fill'],
                ['value' => 'adjustment', 'label' => 'Adjustment'],
            ],
            DataDictionary::GROUP_ROUTE_SCHEDULED_DAY => [
                'Monday',
                'Tuesday',
                'Wednesday',
                'Thursday',
                'Friday',
                'Saturday',
                'Sunday',
            ],
            DataDictionary::GROUP_LOCATION_CONTACT_ROLE => [
                'Site Contact',
                'Manager',
                'Maintenance',
                'Billing',
                'Security',
                'Emergency Contact',
                'Other',
            ],
            DataDictionary::GROUP_LOCATION_DOCUMENT_TYPE => [
                'Contract',
                'Insurance',
                'Access Instructions',
                'Agreement',
                'Photo',
                'Other',
            ],
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
            'machine_type' => [
                'Soda',
                'Snack',
                'Combo',
            ],
            DataDictionary::GROUP_SERVICE_TYPE => [
                ['value' => Service::TYPE_LOCATION, 'label' => 'Location Service'],
                ['value' => Service::TYPE_MAINTENANCE, 'label' => 'Maintenance Service'],
            ],
        ];
    }

    protected function seedGroup(string $group, array $values): void
    {
        foreach (array_values($values) as $index => $entry) {
            $value = is_array($entry) ? $entry['value'] : $entry;
            $label = is_array($entry) ? ($entry['label'] ?? $value) : $entry;

            DataDictionary::query()->updateOrCreate(
                [
                    'account_id' => null,
                    'name' => $group,
                    'value' => $value,
                ],
                [
                    'label' => $label,
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                ],
            );
        }
    }
}

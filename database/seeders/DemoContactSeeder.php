<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\LocationContact;
use Illuminate\Support\Facades\DB;

class DemoContactSeeder extends DemoSeeder
{
    public function run(): void
    {
        $accountId = $this->demoAccount()->id;

        foreach ($this->contacts() as $contact) {
            Contact::query()->updateOrCreate(
                [
                    'account_id' => $accountId,
                    'email' => $contact['email'],
                ],
                [
                    'first_name' => $contact['first_name'],
                    'last_name' => $contact['last_name'],
                    'organization' => $contact['organization'],
                    'title' => $contact['title'],
                    'phone' => $contact['phone'],
                    'mobile_phone' => null,
                    'notes' => $contact['notes'] ?? null,
                ],
            );
        }

        DB::transaction(function () use ($accountId) {
            foreach ($this->locationContacts() as $relationship) {
                $location = $this->locationForAccount($accountId, $relationship['location_name']);
                $contact = Contact::query()
                    ->where('account_id', $accountId)
                    ->where('email', $relationship['email'])
                    ->firstOrFail();

                LocationContact::query()->updateOrCreate(
                    [
                        'account_id' => $accountId,
                        'location_id' => $location->id,
                        'contact_id' => $contact->id,
                        'contact_role' => $relationship['contact_role'],
                    ],
                    [
                        'is_primary' => $relationship['is_primary'],
                        'notes' => $relationship['notes'] ?? null,
                    ],
                );

                if ($relationship['is_primary']) {
                    $location->update([
                        'contact_name' => $this->displayName($contact),
                        'contact_phone' => $contact->phone,
                        'contact_email' => $contact->email,
                    ]);
                }
            }
        });
    }

    protected function contacts(): array
    {
        return [
            [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'organization' => 'Main Office',
                'title' => 'Office Manager',
                'email' => 'jane.smith@example.com',
                'phone' => '555-111-1000',
            ],
            [
                'first_name' => 'Carlos',
                'last_name' => 'Rivera',
                'organization' => 'Tech Center',
                'title' => 'Facilities Manager',
                'email' => 'carlos.rivera@example.com',
                'phone' => '555-111-2000',
            ],
            [
                'first_name' => 'Angela',
                'last_name' => 'Brown',
                'organization' => 'University Hall',
                'title' => 'Site Coordinator',
                'email' => 'angela.brown@example.com',
                'phone' => '555-111-3000',
            ],
            [
                'first_name' => 'Security',
                'last_name' => 'Desk',
                'organization' => 'City Gym',
                'title' => 'Security',
                'email' => 'security@example.com',
                'phone' => '555-111-4000',
            ],
            [
                'first_name' => 'Billing',
                'last_name' => 'Department',
                'organization' => 'Shared Billing',
                'title' => 'Billing Contact',
                'email' => 'billing@example.com',
                'phone' => '555-111-5000',
            ],
        ];
    }

    protected function locationContacts(): array
    {
        return [
            [
                'location_name' => 'Main Office',
                'email' => 'jane.smith@example.com',
                'contact_role' => 'Site Contact',
                'is_primary' => true,
            ],
            [
                'location_name' => 'Main Office',
                'email' => 'billing@example.com',
                'contact_role' => 'Billing',
                'is_primary' => false,
            ],
            [
                'location_name' => 'Tech Center',
                'email' => 'carlos.rivera@example.com',
                'contact_role' => 'Manager',
                'is_primary' => true,
            ],
            [
                'location_name' => 'Tech Center',
                'email' => 'billing@example.com',
                'contact_role' => 'Billing',
                'is_primary' => false,
            ],
            [
                'location_name' => 'University Hall',
                'email' => 'angela.brown@example.com',
                'contact_role' => 'Site Contact',
                'is_primary' => true,
            ],
            [
                'location_name' => 'City Gym',
                'email' => 'security@example.com',
                'contact_role' => 'Security',
                'is_primary' => true,
            ],
            [
                'location_name' => 'City Gym',
                'email' => 'billing@example.com',
                'contact_role' => 'Billing',
                'is_primary' => false,
            ],
            [
                'location_name' => 'Medical Plaza',
                'email' => 'billing@example.com',
                'contact_role' => 'Billing',
                'is_primary' => true,
            ],
        ];
    }

    protected function displayName(Contact $contact): string
    {
        return trim(implode(' ', array_filter([$contact->first_name, $contact->last_name])));
    }
}

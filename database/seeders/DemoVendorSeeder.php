<?php

namespace Database\Seeders;

use App\Models\Vendor;

class DemoVendorSeeder extends DemoSeeder
{
    public function run(): void
    {
        $accountId = $this->demoAccount()->id;

        foreach ($this->vendors() as $vendor) {
            Vendor::query()->updateOrCreate(
                [
                    'account_id' => $accountId,
                    'vendor_name' => $vendor['vendor_name'],
                ],
                [
                    'location' => $vendor['location'],
                ],
            );
        }
    }

    protected function vendors(): array
    {
        return [
            ['vendor_name' => 'Costco Business Center', 'location' => 'Arlington, VA'],
            ['vendor_name' => "Sam's Club", 'location' => 'Alexandria, VA'],
            ['vendor_name' => 'Vistar', 'location' => 'Jessup, MD'],
            ['vendor_name' => 'Coca-Cola Distributor', 'location' => 'Washington, DC'],
            ['vendor_name' => 'Pepsi Distributor', 'location' => 'Landover, MD'],
        ];
    }
}

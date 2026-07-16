<?php

namespace Database\Seeders;

use App\Models\Product;

class DemoProductSeeder extends DemoSeeder
{
    public function run(): void
    {
        $accountId = $this->demoAccount()->id;

        foreach ($this->products() as $product) {
            $vendor = $this->vendorForAccount($accountId, $product['vendor_name']);

            Product::query()->updateOrCreate(
                [
                    'account_id' => $accountId,
                    'sku' => $product['sku'],
                ],
                [
                    'vendor_id' => $vendor->id,
                    'category' => $product['category'],
                    'brand' => $product['brand'],
                    'product_name' => $product['product_name'],
                    'size' => $product['size'],
                    'package_type' => $product['package_type'],
                    'barcode' => $product['barcode'],
                ],
            );
        }
    }

    protected function products(): array
    {
        return [
            [
                'vendor_name' => 'Coca-Cola Distributor',
                'category' => 'Soda',
                'brand' => 'Coca-Cola',
                'sku' => 'COKE-12-CAN',
                'product_name' => 'Coca-Cola 12 oz',
                'size' => '12 oz',
                'package_type' => 'Can',
                'barcode' => '049000028911',
            ],
            [
                'vendor_name' => 'Coca-Cola Distributor',
                'category' => 'Soda',
                'brand' => 'Coca-Cola',
                'sku' => 'DIETCOKE-12-CAN',
                'product_name' => 'Diet Coke 12 oz',
                'size' => '12 oz',
                'package_type' => 'Can',
                'barcode' => '049000006032',
            ],
            [
                'vendor_name' => 'Pepsi Distributor',
                'category' => 'Soda',
                'brand' => 'Pepsi',
                'sku' => 'PEPSI-12-CAN',
                'product_name' => 'Pepsi 12 oz',
                'size' => '12 oz',
                'package_type' => 'Can',
                'barcode' => '012000001296',
            ],
            [
                'vendor_name' => 'Pepsi Distributor',
                'category' => 'Water',
                'brand' => 'Aquafina',
                'sku' => 'AQUAFINA-169-BTL',
                'product_name' => 'Aquafina Water 16.9 oz',
                'size' => '16.9 oz',
                'package_type' => 'Bottle',
                'barcode' => '012000504564',
            ],
            [
                'vendor_name' => 'Pepsi Distributor',
                'category' => 'Sports Drink',
                'brand' => 'Gatorade',
                'sku' => 'GATORADE-FP-20-BTL',
                'product_name' => 'Gatorade Fruit Punch 20 oz',
                'size' => '20 oz',
                'package_type' => 'Bottle',
                'barcode' => '052000026420',
            ],
            [
                'vendor_name' => 'Vistar',
                'category' => 'Chips',
                'brand' => "Lay's",
                'sku' => 'LAYS-CLASSIC-15-BAG',
                'product_name' => "Lay's Classic 1.5 oz",
                'size' => '1.5 oz',
                'package_type' => 'Bag',
                'barcode' => '028400038324',
            ],
            [
                'vendor_name' => 'Vistar',
                'category' => 'Chips',
                'brand' => 'Doritos',
                'sku' => 'DORITOS-NACHO-175-BAG',
                'product_name' => 'Doritos Nacho Cheese 1.75 oz',
                'size' => '1.75 oz',
                'package_type' => 'Bag',
                'barcode' => '028400589245',
            ],
            [
                'vendor_name' => 'Vistar',
                'category' => 'Candy Bar',
                'brand' => 'Snickers',
                'sku' => 'SNICKERS-186-BAR',
                'product_name' => 'Snickers 1.86 oz',
                'size' => '1.86 oz',
                'package_type' => 'Bar',
                'barcode' => '040000002435',
            ],
            [
                'vendor_name' => 'Vistar',
                'category' => 'Candy',
                'brand' => "M&M's",
                'sku' => 'MMS-PEANUT-174-BAG',
                'product_name' => "M&M's Peanut 1.74 oz",
                'size' => '1.74 oz',
                'package_type' => 'Bag',
                'barcode' => '040000422837',
            ],
            [
                'vendor_name' => 'Vistar',
                'category' => 'Cookies',
                'brand' => 'Oreo',
                'sku' => 'OREO-24-PACK',
                'product_name' => 'Oreo 2.4 oz',
                'size' => '2.4 oz',
                'package_type' => 'Pack',
                'barcode' => '044000032643',
            ],
        ];
    }
}

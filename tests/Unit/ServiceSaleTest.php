<?php

namespace Tests\Unit;

use App\Models\ServiceSale;
use PHPUnit\Framework\TestCase;

class ServiceSaleTest extends TestCase
{
    public function test_service_sale_uses_spoilage_instead_of_legacy_sales_fields(): void
    {
        // Lock the model contract so spoilage stays available while removed legacy fields stay gone.
        $sale = new ServiceSale();

        $this->assertContains('spoilage', $sale->getFillable());
        $this->assertArrayHasKey('spoilage', $sale->getCasts());
        $this->assertNotContains('inventory_additions', $sale->getFillable());
        $this->assertArrayNotHasKey('inventory_additions', $sale->getCasts());
        $this->assertNotContains('non_sale_removals', $sale->getFillable());
        $this->assertArrayNotHasKey('non_sale_removals', $sale->getCasts());
    }
}

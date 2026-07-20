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

    public function test_service_sale_exposes_clear_display_labels_without_changing_internal_status_values(): void
    {
        // Keep persisted statuses stable while mapping baseline rows to clearer UI copy.
        $baselineSale = new ServiceSale(['calculation_status' => ServiceSale::CALCULATION_BASELINE]);
        $calculatedSale = new ServiceSale(['calculation_status' => ServiceSale::CALCULATION_CALCULATED]);

        $this->assertTrue($baselineSale->isBaseline());
        $this->assertSame(ServiceSale::CALCULATION_BASELINE, $baselineSale->calculation_status);
        $this->assertSame('Initial Installation', $baselineSale->calculation_status_label);
        $this->assertSame('Calculated', $calculatedSale->calculation_status_label);
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_locations', function (Blueprint $table) {
            $table->unsignedInteger('service_interval_days')->nullable()->after('zip_code');
            $table->date('service_anchor_date')->nullable()->after('service_interval_days');
            $table->decimal('sales_tax_rate', 6, 4)->nullable()->after('service_anchor_date');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_locations', function (Blueprint $table) {
            $table->dropColumn([
                'service_interval_days',
                'service_anchor_date',
                'sales_tax_rate',
            ]);
        });
    }
};

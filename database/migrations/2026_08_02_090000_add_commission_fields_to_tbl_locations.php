<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_locations', function (Blueprint $table) {
            $table->decimal('commission_rate', 6, 4)->nullable()->after('sales_tax_rate');
            $table->boolean('commission_on_net')->default(false)->after('commission_rate');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_locations', function (Blueprint $table) {
            $table->dropColumn(['commission_rate', 'commission_on_net']);
        });
    }
};

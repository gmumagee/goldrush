<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_products', function (Blueprint $table) {
            $table->string('category', 100)->nullable()->after('vendor_id');
            $table->string('brand', 100)->nullable()->after('category');
            $table->string('size', 100)->nullable()->after('product_name');
            $table->string('package_type', 100)->nullable()->after('size');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_products', function (Blueprint $table) {
            $table->dropColumn([
                'category',
                'brand',
                'size',
                'package_type',
            ]);
        });
    }
};

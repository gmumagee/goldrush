<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tbl_bins', 'price')) {
            Schema::table('tbl_bins', function (Blueprint $table) {
                $table->decimal('price', 8, 2)
                    ->nullable()
                    ->after('capacity');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tbl_bins', 'price')) {
            Schema::table('tbl_bins', function (Blueprint $table) {
                $table->dropColumn('price');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add a durable spoilage field so count transactions can separate unusable units from saleable counts.
        if (! Schema::hasColumn('tbl_transactions', 'spoilage')) {
            Schema::table('tbl_transactions', function (Blueprint $table) {
                $table->unsignedInteger('spoilage')
                    ->default(0)
                    ->after('quantity');
            });
        }
    }

    public function down(): void
    {
        // Drop the spoilage field if the count workflow is rolled back.
        if (Schema::hasColumn('tbl_transactions', 'spoilage')) {
            Schema::table('tbl_transactions', function (Blueprint $table) {
                $table->dropColumn('spoilage');
            });
        }
    }
};

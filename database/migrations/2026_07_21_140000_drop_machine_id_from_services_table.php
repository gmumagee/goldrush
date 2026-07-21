<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_services', function (Blueprint $table) {
            $table->dropForeign(['machine_id']);
            $table->dropIndex(['machine_id']);
        });

        Schema::table('tbl_services', function (Blueprint $table) {
            $table->dropColumn('machine_id');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_services', function (Blueprint $table) {
            $table->foreignId('machine_id')
                ->nullable()
                ->after('location_id')
                ->constrained('tbl_machines')
                ->restrictOnDelete();
        });
    }
};

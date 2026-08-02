<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_machines', function (Blueprint $table) {
            $table->string('key_number', 50)->nullable()->after('serial_number');
            $table->string('telemetry_id', 100)->nullable()->after('key_number');
            $table->unique(['account_id', 'telemetry_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tbl_machines', function (Blueprint $table) {
            $table->dropUnique(['account_id', 'telemetry_id']);
            $table->dropColumn(['key_number', 'telemetry_id']);
        });
    }
};

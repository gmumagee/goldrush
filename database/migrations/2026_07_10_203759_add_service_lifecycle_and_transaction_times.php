<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Track the service lifecycle explicitly so opening, closing, and bin
        // transactions can be audited independently from the planned date.
        Schema::table('tbl_services', function (Blueprint $table) {
            $table->dateTime('opened_at')->nullable()->after('service_date');
            $table->dateTime('closed_at')->nullable()->after('opened_at');
        });

        Schema::table('tbl_transactions', function (Blueprint $table) {
            $table->dateTime('transaction_at')->nullable()->after('quantity');
        });

        DB::table('tbl_transactions')
            ->leftJoin('tbl_services', 'tbl_services.id', '=', 'tbl_transactions.service_id')
            ->update([
                'tbl_transactions.transaction_at' => DB::raw('COALESCE(tbl_services.opened_at, tbl_services.service_date, NOW())'),
            ]);

        Schema::table('tbl_transactions', function (Blueprint $table) {
            $table->dateTime('transaction_at')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_transactions', function (Blueprint $table) {
            $table->dropColumn('transaction_at');
        });

        Schema::table('tbl_services', function (Blueprint $table) {
            $table->dropColumn(['opened_at', 'closed_at']);
        });
    }
};

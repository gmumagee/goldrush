<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_transactions', function (Blueprint $table) {
            $table->foreignId('machine_id')
                ->nullable()
                ->after('service_id');
        });

        DB::table('tbl_transactions')
            ->join('tbl_bins', 'tbl_bins.id', '=', 'tbl_transactions.bin_id')
            ->select('tbl_transactions.id', 'tbl_bins.machine_id')
            ->orderBy('tbl_transactions.id')
            ->get()
            ->each(function (object $transaction): void {
                DB::table('tbl_transactions')
                    ->where('id', $transaction->id)
                    ->update(['machine_id' => $transaction->machine_id]);
            });

        if (DB::table('tbl_transactions')->whereNull('machine_id')->exists()) {
            throw new RuntimeException('Unable to backfill machine_id for every transaction row.');
        }

        Schema::table('tbl_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('machine_id')->nullable(false)->change();
            $table->foreign('machine_id')
                ->references('id')
                ->on('tbl_machines')
                ->restrictOnDelete();
            $table->dropConstrainedForeignId('warehouse_id');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_transactions', function (Blueprint $table) {
            $table->foreignId('warehouse_id')
                ->nullable()
                ->after('service_id')
                ->constrained('tbl_warehouses')
                ->nullOnDelete();
        });

        Schema::table('tbl_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('machine_id');
        });
    }
};

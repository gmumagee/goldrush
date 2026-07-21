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

        $fallbackTransactionTimestamp = now()->toDateTimeString();

        // Backfill row-by-row so the migration stays portable across MySQL and
        // the SQLite test database, both of which differ on joined UPDATE support.
        foreach (
            DB::table('tbl_transactions')
                ->leftJoin('tbl_services', 'tbl_services.id', '=', 'tbl_transactions.service_id')
                ->select([
                    'tbl_transactions.id',
                    'tbl_services.opened_at',
                    'tbl_services.service_date',
                ])
                ->orderBy('tbl_transactions.id')
                ->cursor() as $transaction
        ) {
            DB::table('tbl_transactions')
                ->where('id', $transaction->id)
                ->update([
                    'transaction_at' => $transaction->opened_at
                        ?? $transaction->service_date
                        ?? $fallbackTransactionTimestamp,
                ]);
        }

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

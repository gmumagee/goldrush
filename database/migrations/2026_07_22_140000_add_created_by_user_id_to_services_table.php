<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_services', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained('tbl_users')
                ->nullOnDelete();

            $table->index('created_by_user_id');
        });

        DB::table('tbl_services')->update([
            'created_by_user_id' => DB::raw('user_id'),
        ]);
    }

    public function down(): void
    {
        Schema::table('tbl_services', function (Blueprint $table) {
            $table->dropForeign(['created_by_user_id']);
            $table->dropIndex(['created_by_user_id']);
            $table->dropColumn('created_by_user_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Expand the original lookup table so status dropdowns can be driven
        // from active global or account-scoped dictionary rows.
        Schema::table('tbl_data_dictionary', function (Blueprint $table) {
            if (! Schema::hasColumn('tbl_data_dictionary', 'account_id')) {
                $table->foreignId('account_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('tbl_accounts')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('tbl_data_dictionary', 'label')) {
                $table->string('label', 255)->nullable()->after('value');
            }

            if (! Schema::hasColumn('tbl_data_dictionary', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('label');
            }

            if (! Schema::hasColumn('tbl_data_dictionary', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('sort_order');
            }

            if (! Schema::hasColumn('tbl_data_dictionary', 'created_at')) {
                $table->timestamp('created_at')->nullable()->after('is_active');
            }

            if (! Schema::hasColumn('tbl_data_dictionary', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });

        // Backfill new columns so existing rows remain valid lookup options.
        DB::table('tbl_data_dictionary')
            ->whereNull('label')
            ->update(['label' => DB::raw('value')]);

        DB::table('tbl_data_dictionary')
            ->whereNull('created_at')
            ->update([
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        Schema::table('tbl_data_dictionary', function (Blueprint $table) {
            $table->dropUnique('tbl_data_dictionary_name_value_unique');
            $table->unique(['account_id', 'name', 'value'], 'tbl_data_dictionary_account_name_value_unique');
            $table->index(['name', 'is_active'], 'tbl_data_dictionary_name_is_active_index');
            $table->index(['account_id', 'name', 'is_active'], 'tbl_data_dictionary_account_name_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_data_dictionary', function (Blueprint $table) {
            $table->dropIndex('tbl_data_dictionary_account_name_active_index');
            $table->dropIndex('tbl_data_dictionary_name_is_active_index');
            $table->dropUnique('tbl_data_dictionary_account_name_value_unique');
            $table->unique(['name', 'value'], 'tbl_data_dictionary_name_value_unique');
        });

        Schema::table('tbl_data_dictionary', function (Blueprint $table) {
            if (Schema::hasColumn('tbl_data_dictionary', 'account_id')) {
                $table->dropConstrainedForeignId('account_id');
            }

            if (Schema::hasColumn('tbl_data_dictionary', 'label')) {
                $table->dropColumn('label');
            }

            if (Schema::hasColumn('tbl_data_dictionary', 'sort_order')) {
                $table->dropColumn('sort_order');
            }

            if (Schema::hasColumn('tbl_data_dictionary', 'is_active')) {
                $table->dropColumn('is_active');
            }

            if (Schema::hasColumn('tbl_data_dictionary', 'created_at')) {
                $table->dropColumn('created_at');
            }

            if (Schema::hasColumn('tbl_data_dictionary', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
        });
    }
};

<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('machine_limit')->nullable();
            $table->string('display_price', 100);
            $table->unsignedInteger('sort_order');
            $table->timestamps();
        });

        DB::table('plans')->insert([
            [
                'id' => Plan::FREE_ID,
                'name' => 'Free',
                'slug' => Plan::FREE_SLUG,
                'machine_limit' => 10,
                'display_price' => '$0/mo',
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Plan::STARTER_ID,
                'name' => 'Starter',
                'slug' => Plan::STARTER_SLUG,
                'machine_limit' => 25,
                'display_price' => '$99/mo',
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Plan::PRO_ID,
                'name' => 'Pro',
                'slug' => Plan::PRO_SLUG,
                'machine_limit' => null,
                'display_price' => '$249/mo',
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Schema::table('tbl_accounts', function (Blueprint $table) {
            $table->foreignId('plan_id')
                ->default(Plan::FREE_ID)
                ->after('id')
                ->constrained('plans')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
        });

        Schema::dropIfExists('plans');
    }
};

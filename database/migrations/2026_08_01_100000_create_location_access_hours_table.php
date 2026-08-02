<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_location_access_hours', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')
                ->constrained('tbl_accounts')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('location_id')
                ->constrained('tbl_locations')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('day_of_week');
            $table->time('opens_at');
            $table->time('closes_at');

            $table->unique(['location_id', 'day_of_week'], 'location_access_hours_unique');
            $table->index('account_id');
            $table->index('location_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_location_access_hours');
    }
};

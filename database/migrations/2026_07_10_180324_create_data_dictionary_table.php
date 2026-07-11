<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Global lookup pairs let the application reuse shared terms such as
        // machine types and status values without tying them to an account.
        Schema::create('tbl_data_dictionary', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('value', 255);

            $table->index('name');
            $table->unique(['name', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_data_dictionary');
    }
};

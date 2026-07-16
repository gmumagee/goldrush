<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')
                ->constrained('tbl_accounts')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('organization')->nullable();
            $table->string('title')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('mobile_phone', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Contact search stays fast when account-scoped lists grow.
            $table->index('account_id');
            $table->index('email');
            $table->index(['account_id', 'last_name']);
        });

        Schema::create('tbl_location_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')
                ->constrained('tbl_accounts')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('location_id')
                ->constrained('tbl_locations')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('contact_id')
                ->constrained('tbl_contacts')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('contact_role', 100)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Relationship uniqueness is enforced per account, location, contact, and role.
            $table->unique(
                ['account_id', 'location_id', 'contact_id', 'contact_role'],
                'location_contact_role_unique'
            );
            $table->index('account_id');
            $table->index('location_id');
            $table->index('contact_id');
            $table->index('contact_role');
            $table->index('is_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_location_contacts');
        Schema::dropIfExists('tbl_contacts');
    }
};

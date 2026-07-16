<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_location_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')
                ->constrained('tbl_accounts')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('location_id')
                ->constrained('tbl_locations')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('document_type', 100)->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('storage_disk', 50)->default('private');
            $table->string('storage_path', 500);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();

            $table->foreignId('uploaded_by_user_id')
                ->nullable()
                ->constrained('tbl_users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->timestamp('created_at')->nullable();

            $table->index('account_id');
            $table->index('location_id');
            $table->index('document_type');
            $table->index('uploaded_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_location_documents');
    }
};

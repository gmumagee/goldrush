<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_calendar_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')
                ->constrained('tbl_accounts')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('event_type', 100);
            $table->string('title');
            $table->text('description')->nullable();

            $table->dateTime('start_at');
            $table->dateTime('end_at')->nullable();
            $table->boolean('all_day')->default(false);

            $table->string('status', 50)->default('Scheduled');
            $table->string('priority', 50)->nullable();

            $table->foreignId('assigned_user_id')
                ->nullable()
                ->constrained('tbl_users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('location_id')
                ->nullable()
                ->constrained('tbl_locations')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('warehouse_id')
                ->nullable()
                ->constrained('tbl_warehouses')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('route_id')
                ->nullable()
                ->constrained('tbl_routes')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('tbl_users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index('account_id');
            $table->index('event_type');
            $table->index('status');
            $table->index('start_at');
            $table->index('assigned_user_id');
            $table->index('location_id');
            $table->index('warehouse_id');
            $table->index('route_id');
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('tbl_calendar_reminders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')
                ->constrained('tbl_accounts')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('calendar_event_id')
                ->constrained('tbl_calendar_events')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->dateTime('remind_at');

            $table->string('reminder_type', 100)->default('dashboard');
            $table->string('status', 50)->default('Pending');

            $table->foreignId('assigned_user_id')
                ->nullable()
                ->constrained('tbl_users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->text('message')->nullable();
            $table->dateTime('dismissed_at')->nullable();

            $table->foreignId('dismissed_by_user_id')
                ->nullable()
                ->constrained('tbl_users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->timestamps();

            $table->index('account_id');
            $table->index('calendar_event_id');
            $table->index('remind_at');
            $table->index('status');
            $table->index('assigned_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_calendar_reminders');
        Schema::dropIfExists('tbl_calendar_events');
    }
};

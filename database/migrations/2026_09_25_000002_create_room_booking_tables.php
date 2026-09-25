<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('calendar_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32)->default('microsoft365');
            $table->string('tenant_id')->nullable();
            $table->string('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'provider']);
        });

        Schema::create('meeting_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->json('amenities')->nullable();
            $table->string('color', 16)->default('#2563eb');
            $table->boolean('is_active')->default(true);
            $table->boolean('public_booking_enabled')->default(true);
            $table->boolean('requires_approval')->default(false);
            $table->string('booking_token', 64)->unique();
            $table->unsignedSmallInteger('min_duration_minutes')->default(15);
            $table->unsignedSmallInteger('max_duration_minutes')->default(240);
            $table->string('opens_at', 5)->default('07:00');
            $table->string('closes_at', 5)->default('21:00');
            $table->foreignId('calendar_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_calendar_id')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'is_active']);
        });

        Schema::create('room_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_room_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('organizer_name')->nullable();
            $table->string('organizer_email')->nullable();
            $table->unsignedSmallInteger('attendees')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 16)->default('confirmed');
            $table->string('source', 16)->default('manual');
            $table->string('external_id')->nullable();
            $table->string('external_change_key')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('sync_error', 500)->nullable();
            $table->timestamps();

            $table->index(['meeting_room_id', 'starts_at']);
            $table->index(['team_id', 'starts_at']);
            $table->unique(['meeting_room_id', 'external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_bookings');
        Schema::dropIfExists('meeting_rooms');
        Schema::dropIfExists('calendar_connections');
    }
};

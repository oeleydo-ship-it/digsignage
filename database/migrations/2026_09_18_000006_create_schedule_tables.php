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
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained('channels')->nullOnDelete();
            $table->foreignId('playlist_id')->nullable()->constrained('playlists')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('content_type')->default('channel');
            $table->string('timezone')->default('UTC');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('start_time', 8)->default('00:00:00');
            $table->string('end_time', 8)->default('23:59:00');
            $table->string('recurrence')->default('daily');
            $table->json('weekdays')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'is_enabled', 'priority']);
        });

        Schema::create('schedule_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $table->string('target_type');
            $table->foreignId('screen_id')->nullable()->constrained('screens')->cascadeOnDelete();
            $table->foreignId('screen_group_id')->nullable()->constrained('screen_groups')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['schedule_id', 'target_type']);
            $table->index(['team_id', 'screen_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_targets');
        Schema::dropIfExists('schedules');
    }
};

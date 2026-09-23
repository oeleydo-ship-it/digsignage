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
        Schema::create('emergencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('stopped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('image_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('video_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('title');
            $table->text('message')->nullable();
            $table->text('instructions')->nullable();
            $table->string('background', 32)->nullable();
            $table->string('severity')->default('emergency');
            $table->string('status')->default('draft');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status']);
            $table->index(['status', 'starts_at']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('emergency_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('emergency_id')->constrained('emergencies')->cascadeOnDelete();
            $table->string('target_type');
            $table->foreignId('screen_id')->nullable()->constrained('screens')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['emergency_id', 'target_type']);
        });

        Schema::create('emergency_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('emergency_id')->constrained('emergencies')->cascadeOnDelete();
            $table->foreignId('screen_id')->constrained('screens')->cascadeOnDelete();
            $table->foreignId('device_command_id')->nullable()->constrained('device_commands')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();

            $table->unique(['emergency_id', 'screen_id']);
            $table->index(['screen_id', 'status']);
        });

        Schema::create('emergency_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('emergency_id')->constrained('emergencies')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('screen_id')->nullable()->constrained('screens')->nullOnDelete();
            $table->string('action');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['emergency_id', 'created_at']);
            $table->index(['team_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emergency_audits');
        Schema::dropIfExists('emergency_deliveries');
        Schema::dropIfExists('emergency_targets');
        Schema::dropIfExists('emergencies');
    }
};

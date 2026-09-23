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
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('webhook_url', 2048)->nullable();
            $table->string('slack_webhook_url', 2048)->nullable();
            $table->string('min_player_version', 32)->nullable();
            $table->timestamps();
        });

        Schema::create('notification_channel_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('event', 64);
            $table->boolean('email')->default(true);
            $table->boolean('in_app')->default(true);
            $table->boolean('webhook')->default(false);
            $table->boolean('slack')->default(false);
            $table->timestamps();

            $table->unique(['team_id', 'event']);
        });

        Schema::create('in_app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event', 64);
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'team_id', 'read_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('in_app_notifications');
        Schema::dropIfExists('notification_channel_preferences');
        Schema::dropIfExists('notification_settings');
    }
};

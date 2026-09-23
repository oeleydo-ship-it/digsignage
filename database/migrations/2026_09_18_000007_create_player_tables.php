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
        Schema::table('screens', function (Blueprint $table) {
            $table->string('device_token_hash', 64)->nullable()->unique()->after('device_token');
        });

        Schema::create('player_playback_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('screen_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('playlist_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_key')->nullable();
            $table->string('asset_key')->nullable();
            $table->string('title')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('played_at');
            $table->timestamps();

            $table->index(['team_id', 'screen_id', 'played_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_playback_events');

        Schema::table('screens', function (Blueprint $table) {
            $table->dropUnique(['device_token_hash']);
            $table->dropColumn('device_token_hash');
        });
    }
};

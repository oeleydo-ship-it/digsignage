<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('player_playback_events', function (Blueprint $table) {
            $table->string('content_id')->nullable()->after('asset_key');
            $table->foreignId('location_id')->nullable()->after('screen_id')->constrained()->nullOnDelete();
            $table->timestamp('started_at')->nullable()->after('duration_ms');
            $table->timestamp('ended_at')->nullable()->after('started_at');
            $table->string('status', 32)->default('completed')->after('ended_at');

            $table->index(['team_id', 'location_id', 'played_at']);
            $table->index(['team_id', 'content_id', 'played_at']);
            $table->index(['team_id', 'status', 'played_at']);
        });

        DB::table('player_playback_events')->whereNull('started_at')->update([
            'started_at' => DB::raw('played_at'),
            'content_id' => DB::raw('item_key'),
            'status' => 'completed',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_playback_events', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'location_id', 'played_at']);
            $table->dropIndex(['team_id', 'content_id', 'played_at']);
            $table->dropIndex(['team_id', 'status', 'played_at']);
            $table->dropConstrainedForeignId('location_id');
            $table->dropColumn(['content_id', 'started_at', 'ended_at', 'status']);
        });
    }
};

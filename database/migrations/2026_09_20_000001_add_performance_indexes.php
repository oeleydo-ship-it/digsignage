<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Composite indexes matching the hottest query paths: the every-minute
     * command expiry and screen health evaluation jobs, the dashboard
     * invitation lookup, proof-of-play filters, and the media library.
     */
    public function up(): void
    {
        Schema::table('device_commands', function (Blueprint $table) {
            $table->index(['status', 'expires_at'], 'device_commands_status_expires');
        });

        Schema::table('screens', function (Blueprint $table) {
            $table->index(['status', 'last_seen_at'], 'screens_status_last_seen');
        });

        Schema::table('team_invitations', function (Blueprint $table) {
            $table->index(['email', 'accepted_at'], 'team_invitations_email_pending');
        });

        Schema::table('player_playback_events', function (Blueprint $table) {
            $table->index(['team_id', 'playlist_id', 'played_at'], 'playback_team_playlist_played');
            $table->index(['team_id', 'channel_id', 'played_at'], 'playback_team_channel_played');
        });

        Schema::table('media', function (Blueprint $table) {
            $table->index(['team_id', 'archived_at'], 'media_team_archived');
        });

        Schema::table('webhook_deliveries', function (Blueprint $table) {
            $table->index(['team_id', 'status'], 'webhook_deliveries_team_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webhook_deliveries', function (Blueprint $table) {
            $table->dropIndex('webhook_deliveries_team_status');
        });

        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex('media_team_archived');
        });

        Schema::table('player_playback_events', function (Blueprint $table) {
            $table->dropIndex('playback_team_playlist_played');
            $table->dropIndex('playback_team_channel_played');
        });

        Schema::table('team_invitations', function (Blueprint $table) {
            $table->dropIndex('team_invitations_email_pending');
        });

        Schema::table('screens', function (Blueprint $table) {
            $table->dropIndex('screens_status_last_seen');
        });

        Schema::table('device_commands', function (Blueprint $table) {
            $table->dropIndex('device_commands_status_expires');
        });
    }
};

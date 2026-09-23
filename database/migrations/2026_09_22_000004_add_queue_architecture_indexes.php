<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_services', function (Blueprint $table) {
            $table->index(
                ['team_id', 'is_active', 'location_id'],
                'queue_services_team_active_location',
            );
        });

        Schema::table('queue_priorities', function (Blueprint $table) {
            $table->index(
                ['team_id', 'is_active', 'sort_order'],
                'queue_priorities_team_active_sort',
            );
        });

        Schema::table('queue_counters', function (Blueprint $table) {
            $table->index(
                ['team_id', 'status', 'location_id'],
                'queue_counters_team_status_location',
            );
        });

        Schema::table('queue_counter_service', function (Blueprint $table) {
            $table->index(
                ['queue_service_id', 'counter_id'],
                'queue_counter_service_service_counter',
            );
        });

        Schema::table('queue_kiosks', function (Blueprint $table) {
            $table->index(
                ['team_id', 'is_active', 'location_id'],
                'queue_kiosks_team_active_location',
            );
        });

        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->index(
                ['team_id', 'queue_service_id', 'status', 'created_at', 'id'],
                'queue_tickets_team_service_status_created',
            );
            $table->index(
                ['counter_id', 'status', 'id'],
                'queue_tickets_counter_status_id',
            );
            $table->index(
                ['team_id', 'location_id', 'status'],
                'queue_tickets_team_location_status',
            );
            $table->index(
                ['team_id', 'status', 'called_at', 'id'],
                'queue_tickets_team_status_called',
            );
            $table->index(
                ['team_id', 'created_at'],
                'queue_tickets_team_created',
            );
        });

        Schema::table('queue_ticket_events', function (Blueprint $table) {
            $table->index(
                ['queue_ticket_id', 'created_at'],
                'queue_ticket_events_ticket_created',
            );
        });

        Schema::table('queue_appointments', function (Blueprint $table) {
            $table->index(
                ['team_id', 'status', 'scheduled_at'],
                'queue_appointments_team_status_scheduled',
            );
            $table->index(
                ['team_id', 'queue_service_id', 'scheduled_at'],
                'queue_appointments_team_service_scheduled',
            );
        });

        Schema::table('queue_notification_rules', function (Blueprint $table) {
            $table->index(
                ['event', 'is_enabled', 'team_id'],
                'queue_notification_rules_event_enabled_team',
            );
        });

        Schema::table('queue_notification_deliveries', function (Blueprint $table) {
            $table->index(
                ['team_id', 'created_at'],
                'queue_notification_deliveries_team_created',
            );
        });
    }

    public function down(): void
    {
        Schema::table('queue_notification_deliveries', function (Blueprint $table) {
            $table->dropIndex('queue_notification_deliveries_team_created');
        });

        Schema::table('queue_notification_rules', function (Blueprint $table) {
            $table->dropIndex('queue_notification_rules_event_enabled_team');
        });

        Schema::table('queue_appointments', function (Blueprint $table) {
            $table->dropIndex('queue_appointments_team_service_scheduled');
            $table->dropIndex('queue_appointments_team_status_scheduled');
        });

        Schema::table('queue_ticket_events', function (Blueprint $table) {
            $table->dropIndex('queue_ticket_events_ticket_created');
        });

        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->dropIndex('queue_tickets_team_created');
            $table->dropIndex('queue_tickets_team_status_called');
            $table->dropIndex('queue_tickets_team_location_status');
            $table->dropIndex('queue_tickets_counter_status_id');
            $table->dropIndex('queue_tickets_team_service_status_created');
        });

        Schema::table('queue_kiosks', function (Blueprint $table) {
            $table->dropIndex('queue_kiosks_team_active_location');
        });

        Schema::table('queue_counter_service', function (Blueprint $table) {
            $table->dropIndex('queue_counter_service_service_counter');
        });

        Schema::table('queue_counters', function (Blueprint $table) {
            $table->dropIndex('queue_counters_team_status_location');
        });

        Schema::table('queue_priorities', function (Blueprint $table) {
            $table->dropIndex('queue_priorities_team_active_sort');
        });

        Schema::table('queue_services', function (Blueprint $table) {
            $table->dropIndex('queue_services_team_active_location');
        });
    }
};

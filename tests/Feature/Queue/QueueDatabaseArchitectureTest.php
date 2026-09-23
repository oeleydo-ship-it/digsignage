<?php

namespace Tests\Feature\Queue;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class QueueDatabaseArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_schema_reuses_platform_entities_and_contains_normalized_module_tables(): void
    {
        $this->assertTrue(Schema::hasTable('teams'));
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('locations'));
        $this->assertTrue(Schema::hasTable('screens'));
        $this->assertTrue(Schema::hasTable('designs'));
        $this->assertTrue(Schema::hasTable('playlists'));
        $this->assertTrue(Schema::hasTable('channels'));

        foreach ([
            'queue_settings',
            'queue_services',
            'queue_priorities',
            'queue_counters',
            'queue_counter_service',
            'queue_tickets',
            'queue_ticket_events',
            'queue_kiosks',
            'queue_appointments',
            'queue_notification_rules',
            'queue_notification_deliveries',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected normalized table [{$table}] to exist.");
        }

        $this->assertFalse(
            Schema::hasTable('queue_displays'),
            'Queue displays must reuse the existing signage deployment entities.',
        );
    }

    public function test_queue_hot_paths_have_query_shaped_indexes(): void
    {
        $expectedIndexes = [
            'queue_services' => [
                'queue_services_team_active_location' => ['team_id', 'is_active', 'location_id'],
            ],
            'queue_priorities' => [
                'queue_priorities_team_active_sort' => ['team_id', 'is_active', 'sort_order'],
            ],
            'queue_counters' => [
                'queue_counters_team_status_location' => ['team_id', 'status', 'location_id'],
            ],
            'queue_counter_service' => [
                'queue_counter_service_service_counter' => ['queue_service_id', 'counter_id'],
            ],
            'queue_kiosks' => [
                'queue_kiosks_team_active_location' => ['team_id', 'is_active', 'location_id'],
            ],
            'queue_tickets' => [
                'queue_tickets_team_service_status_created' => ['team_id', 'queue_service_id', 'status', 'created_at', 'id'],
                'queue_tickets_counter_status_id' => ['counter_id', 'status', 'id'],
                'queue_tickets_team_location_status' => ['team_id', 'location_id', 'status'],
                'queue_tickets_team_status_called' => ['team_id', 'status', 'called_at', 'id'],
                'queue_tickets_team_created' => ['team_id', 'created_at'],
            ],
            'queue_ticket_events' => [
                'queue_ticket_events_ticket_created' => ['queue_ticket_id', 'created_at'],
            ],
            'queue_appointments' => [
                'queue_appointments_team_status_scheduled' => ['team_id', 'status', 'scheduled_at'],
                'queue_appointments_team_service_scheduled' => ['team_id', 'queue_service_id', 'scheduled_at'],
            ],
            'queue_notification_rules' => [
                'queue_notification_rules_event_enabled_team' => ['event', 'is_enabled', 'team_id'],
            ],
            'queue_notification_deliveries' => [
                'queue_notification_deliveries_team_created' => ['team_id', 'created_at'],
            ],
        ];

        foreach ($expectedIndexes as $table => $indexes) {
            $actual = collect(Schema::getIndexes($table))->keyBy('name');

            foreach ($indexes as $name => $columns) {
                $this->assertTrue($actual->has($name), "Expected index [{$name}] on [{$table}].");
                $this->assertSame($columns, $actual->get($name)['columns']);
            }
        }
    }

    public function test_queue_ownership_and_reusable_entity_foreign_keys_are_enforced(): void
    {
        $expectedForeignKeys = [
            'queue_settings' => ['team_id' => 'teams'],
            'queue_services' => ['team_id' => 'teams', 'location_id' => 'locations'],
            'queue_priorities' => ['team_id' => 'teams'],
            'queue_counters' => [
                'team_id' => 'teams',
                'location_id' => 'locations',
                'assigned_user_id' => 'users',
            ],
            'queue_tickets' => [
                'team_id' => 'teams',
                'location_id' => 'locations',
                'assigned_user_id' => 'users',
                'queue_service_id' => 'queue_services',
                'queue_priority_id' => 'queue_priorities',
                'counter_id' => 'queue_counters',
            ],
            'queue_ticket_events' => [
                'team_id' => 'teams',
                'user_id' => 'users',
                'queue_ticket_id' => 'queue_tickets',
            ],
            'queue_kiosks' => ['team_id' => 'teams', 'location_id' => 'locations'],
            'queue_appointments' => [
                'team_id' => 'teams',
                'location_id' => 'locations',
                'queue_service_id' => 'queue_services',
                'queue_ticket_id' => 'queue_tickets',
            ],
            'queue_notification_rules' => ['team_id' => 'teams'],
            'queue_notification_deliveries' => [
                'team_id' => 'teams',
                'queue_notification_rule_id' => 'queue_notification_rules',
                'queue_ticket_id' => 'queue_tickets',
                'queue_appointment_id' => 'queue_appointments',
            ],
        ];

        foreach ($expectedForeignKeys as $table => $foreignKeys) {
            $actual = collect(Schema::getForeignKeys($table))
                ->mapWithKeys(fn (array $foreignKey): array => [
                    $foreignKey['columns'][0] => $foreignKey['foreign_table'],
                ]);

            foreach ($foreignKeys as $column => $foreignTable) {
                $this->assertSame(
                    $foreignTable,
                    $actual->get($column),
                    "Expected [{$table}.{$column}] to reference [{$foreignTable}].",
                );
            }
        }
    }
}

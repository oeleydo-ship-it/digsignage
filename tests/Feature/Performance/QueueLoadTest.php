<?php

namespace Tests\Feature\Performance;

use App\Actions\Queue\BuildQueueOperationsDashboard;
use App\Enums\QueueTicketSource;
use App\Enums\QueueTicketStatus;
use App\Models\QueueCounter;
use App\Models\QueueService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QueueLoadTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_snapshot_stays_query_bounded_with_one_thousand_waiting_tickets(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $now = now();
        $services = QueueService::factory()->count(5)->create(['team_id' => $team->id]);

        foreach ($services as $service) {
            $counter = QueueCounter::factory()->create(['team_id' => $team->id]);
            $counter->services()->attach($service);
            $rows = [];

            for ($sequence = 1; $sequence <= 200; $sequence++) {
                $rows[] = [
                    'team_id' => $team->id,
                    'queue_service_id' => $service->id,
                    'location_id' => null,
                    'number' => 'S'.$service->id.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
                    'sequence' => $sequence,
                    'numbering_period' => $now->toDateString(),
                    'status' => QueueTicketStatus::Waiting->value,
                    'priority' => 0,
                    'queue_position' => $sequence,
                    'source' => QueueTicketSource::Staff->value,
                    'created_at' => $now->copy()->subSeconds(1000 - $sequence),
                    'updated_at' => $now,
                ];
            }

            DB::table('queue_tickets')->insert($rows);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $snapshot = app(BuildQueueOperationsDashboard::class)->handle($team);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(1000, $snapshot['stats']['waiting']);
        $this->assertCount(5, $snapshot['services']);
        $this->assertCount(5, $snapshot['counters']);
        $this->assertLessThanOrEqual(12, $queries);
    }
}

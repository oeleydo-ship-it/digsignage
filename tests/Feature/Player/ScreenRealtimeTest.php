<?php

namespace Tests\Feature\Player;

use App\Actions\Signage\SaveScreen;
use App\Events\PlayerManifestUpdated;
use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ScreenRealtimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_a_paired_screen_broadcasts_a_manifest_refresh_only_to_that_player(): void
    {
        $user = User::factory()->create();
        $screen = Screen::factory()->paired()->create(['team_id' => $user->currentTeam->id]);
        Event::fake([PlayerManifestUpdated::class]);

        app(SaveScreen::class)->handle($user->currentTeam, [
            'name' => 'Updated reception screen',
        ], $screen);

        Event::assertDispatched(PlayerManifestUpdated::class, function (PlayerManifestUpdated $event) use ($screen): bool {
            return $event->deviceUuid === $screen->device_uuid
                && $event->broadcastAs() === 'manifest.updated'
                && (string) $event->broadcastOn()[0] === 'private-player.'.$screen->device_uuid;
        });
    }

    public function test_player_heartbeat_does_not_broadcast_a_manifest_refresh(): void
    {
        $user = User::factory()->create();
        $screen = Screen::factory()->paired()->create(['team_id' => $user->currentTeam->id]);
        Event::fake([PlayerManifestUpdated::class]);

        $screen->update(['last_seen_at' => now()]);

        Event::assertNotDispatched(PlayerManifestUpdated::class);
    }
}

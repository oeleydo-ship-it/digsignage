<?php

namespace Tests\Feature\Queue;

use App\Enums\TeamRole;
use App\Models\QueueSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueVoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_save_voice_announcement_settings(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)->patch(route('queue.settings.voice.update', $team), [
            'enabled' => true,
            'languages' => ['en-US', 'ar-AE', 'fil-PH', 'hi-IN'],
            'voice' => 'Microsoft Zira',
            'speed' => 1.2,
            'volume' => 0.75,
            'repeat_count' => 2,
            'chime' => false,
            'announcement_delay_seconds' => 2.5,
        ])->assertRedirect();

        $voice = QueueSetting::resolveForTeam($team)->settings['voice'];
        $this->assertTrue($voice['enabled']);
        $this->assertSame(['en-US', 'ar-AE', 'fil-PH', 'hi-IN'], $voice['languages']);
        $this->assertSame(2, $voice['repeat_count']);
        $this->assertFalse($voice['chime']);
        $this->assertSame(2.5, $voice['announcement_delay_seconds']);
    }

    public function test_read_only_member_cannot_save_voice_settings(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $member = User::factory()->create();
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        $this->actingAs($member)->patch(route('queue.settings.voice.update', $team), [
            'enabled' => true,
            'languages' => ['en-US'],
            'voice' => null,
            'speed' => 1,
            'volume' => 1,
            'repeat_count' => 1,
            'chime' => true,
            'announcement_delay_seconds' => 1,
        ])->assertForbidden();
    }

    public function test_announcement_delay_must_be_between_zero_and_thirty_seconds(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)->patch(route('queue.settings.voice.update', $team), [
            'enabled' => true,
            'languages' => ['en-US'],
            'voice' => null,
            'speed' => 1,
            'volume' => 1,
            'repeat_count' => 1,
            'chime' => true,
            'announcement_delay_seconds' => 31,
        ])->assertSessionHasErrors('announcement_delay_seconds');
    }
}

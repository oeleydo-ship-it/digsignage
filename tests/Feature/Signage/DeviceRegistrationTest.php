<?php

namespace Tests\Feature\Signage;

use App\Enums\ScreenOrientation;
use App\Enums\TeamRole;
use App\Models\DeviceRegistration;
use App\Models\Team;
use App\Models\User;
use App\Support\RegistrationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeviceRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_can_start_and_complete_pairing(): void
    {
        $start = $this->postJson('/api/player/v1/registrations');

        $start->assertCreated()->assertJsonStructure(['code', 'expires_at']);

        $code = $start->json('code');
        $user = User::factory()->create();

        $this->getJson('/api/player/v1/registrations/'.$code)
            ->assertOk()
            ->assertJsonPath('status', 'pending');

        $this->actingAs($user)
            ->post(route('screens.pair', $user->currentTeam), [
                'code' => $code,
                'name' => 'Reception TV',
                'orientation' => ScreenOrientation::Landscape->value,
            ])
            ->assertRedirect();

        $paired = $this->getJson('/api/player/v1/registrations/'.$code);

        $paired->assertOk()
            ->assertJsonPath('status', 'paired')
            ->assertJsonStructure(['device_uuid', 'device_token']);

        $this->assertNotNull($paired->json('device_token'));
        $this->assertDatabaseCount('screens', 1);

        $this->getJson('/api/player/v1/registrations/'.$code)
            ->assertOk()
            ->assertJsonPath('status', 'paired')
            ->assertJsonMissingPath('device_token');
    }

    public function test_expired_codes_cannot_be_paired(): void
    {
        $code = 'WXYZ-9999';
        DeviceRegistration::factory()->expired()->create([
            'code_hash' => RegistrationCode::hash($code),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('screens.pair', $user->currentTeam), [
                'code' => $code,
                'name' => 'Late Screen',
                'orientation' => ScreenOrientation::Landscape->value,
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_codes_cannot_be_reused(): void
    {
        $code = 'LMNO-1111';
        $user = User::factory()->create();

        DeviceRegistration::query()->create([
            'code_hash' => RegistrationCode::hash($code),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->actingAs($user)
            ->post(route('screens.pair', $user->currentTeam), [
                'code' => $code,
                'name' => 'First',
                'orientation' => ScreenOrientation::Landscape->value,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('screens.pair', $user->currentTeam), [
                'code' => $code,
                'name' => 'Second',
                'orientation' => ScreenOrientation::Landscape->value,
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_members_cannot_pair_screens(): void
    {
        $start = $this->postJson('/api/player/v1/registrations');
        $code = $start->json('code');

        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        $this->actingAs($member)
            ->post(route('screens.pair', $team), [
                'code' => $code,
                'name' => 'Denied',
                'orientation' => ScreenOrientation::Landscape->value,
            ])
            ->assertForbidden();
    }

    public function test_device_token_is_stored_hashed(): void
    {
        $start = $this->postJson('/api/player/v1/registrations');
        $code = $start->json('code');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('screens.pair', $user->currentTeam), [
                'code' => $code,
                'name' => 'Hashed Token Screen',
                'orientation' => ScreenOrientation::Landscape->value,
            ]);

        $token = $this->getJson('/api/player/v1/registrations/'.$code)->json('device_token');
        $screen = $user->currentTeam->screens()->first();

        $this->assertNotSame($token, $screen->device_token);
        $this->assertTrue(Hash::check($token, $screen->device_token));
    }
}

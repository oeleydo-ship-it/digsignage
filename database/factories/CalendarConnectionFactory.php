<?php

namespace Database\Factories;

use App\Models\CalendarConnection;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarConnection>
 */
class CalendarConnectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'provider' => CalendarConnection::MICROSOFT_365,
            'tenant_id' => '11111111-2222-3333-4444-555555555555',
            'client_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'client_secret' => 'test-secret',
            'is_active' => true,
            'last_synced_at' => null,
            'last_error' => null,
        ];
    }
}

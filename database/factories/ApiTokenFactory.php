<?php

namespace Database\Factories;

use App\Enums\ApiScope;
use App\Models\ApiToken;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiToken>
 */
class ApiTokenFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plain = 'dsg_'.fake()->unique()->sha256();

        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'name' => 'Partner token',
            'token_prefix' => substr($plain, -8),
            'token_hash' => ApiToken::hashToken($plain),
            'scopes' => [ApiScope::ScreensRead->value],
        ];
    }
}

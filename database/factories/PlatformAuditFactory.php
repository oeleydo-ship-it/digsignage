<?php

namespace Database\Factories;

use App\Enums\PlatformAuditAction;
use App\Models\PlatformAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformAudit>
 */
class PlatformAuditFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_id' => User::factory(),
            'action' => PlatformAuditAction::OrganizationUpdated,
            'resource_type' => 'team',
            'resource_id' => 1,
            'before' => null,
            'after' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'created_at' => now(),
        ];
    }
}

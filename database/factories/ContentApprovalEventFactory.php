<?php

namespace Database\Factories;

use App\Enums\ContentApprovalAction;
use App\Models\ContentApprovalEvent;
use App\Models\Playlist;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentApprovalEvent>
 */
class ContentApprovalEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'approvable_type' => (new Playlist)->getMorphClass(),
            'approvable_id' => Playlist::factory(),
            'action' => ContentApprovalAction::Submitted,
            'from_status' => 'draft',
            'to_status' => 'pending_approval',
            'revision' => 1,
            'user_id' => User::factory(),
            'comment' => null,
        ];
    }
}

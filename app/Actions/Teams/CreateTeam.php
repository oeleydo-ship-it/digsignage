<?php

namespace App\Actions\Teams;

use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateTeam
{
    /**
     * Create a new team and add the user as owner.
     */
    public function handle(User $user, string $name, bool $isPersonal = false): Team
    {
        return DB::transaction(function () use ($user, $name, $isPersonal) {
            $team = Team::create([
                'name' => $name,
                'is_personal' => $isPersonal,
                'plan_key' => PlanKey::Starter,
                'subscription_status' => SubscriptionStatus::Trialing,
                'trial_ends_at' => now()->addDays((int) config('billing.trial_days', 14)),
            ]);

            $membership = $team->memberships()->create([
                'user_id' => $user->id,
                'role' => TeamRole::Owner,
            ]);

            $user->switchTeam($team);

            return $team;
        });
    }
}

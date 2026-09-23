<?php

namespace Database\Factories;

use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'is_personal' => false,
            'plan_key' => PlanKey::Enterprise,
            'subscription_status' => SubscriptionStatus::Active,
            'trial_ends_at' => null,
            'bandwidth_used_bytes' => 0,
        ];
    }

    /**
     * Starter plan with an active subscription.
     */
    public function starter(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan_key' => PlanKey::Starter,
            'subscription_status' => SubscriptionStatus::Active,
        ]);
    }

    /**
     * Starter plan currently on a trial.
     */
    public function trialing(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan_key' => PlanKey::Starter,
            'subscription_status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->addDays((int) config('billing.trial_days', 14)),
        ]);
    }

    /**
     * Indicate that the team is a personal team.
     */
    public function personal(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_personal' => true,
        ]);
    }

    /**
     * Indicate that the team has been deleted.
     */
    public function trashed(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now(),
        ]);
    }
}

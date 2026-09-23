<?php

namespace Database\Factories;

use App\Enums\PlanKey;
use App\Models\Plan;
use App\Support\BillingCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $defaults = BillingCatalog::defaults(PlanKey::Starter);

        return [
            'key' => PlanKey::Starter->value,
            ...$defaults,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'number' => 'INV-'.fake()->unique()->numerify('####'),
            'stripe_id' => null,
            'amount_cents' => 2900,
            'currency' => 'usd',
            'status' => 'paid',
            'hosted_invoice_url' => null,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'paid_at' => now(),
        ];
    }
}

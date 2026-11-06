<?php

namespace Database\Factories;

use App\Enums\HoldStatus;
use App\Models\Account;
use App\Models\FundsHold;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FundsHold>
 */
class FundsHoldFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->numberBetween(100, 50_000);

        return [
            'reference' => 'HLD-'.Str::upper(Str::random(12)),
            'account_id' => Account::factory(),
            'customer_id' => fn (array $attributes) => Account::query()->whereKey($attributes['account_id'])->value('user_id'),
            'reason' => 'Demo hold',
            'amount_minor' => $amount,
            'remaining_amount_minor' => $amount,
            'currency' => 'USD',
            'status' => HoldStatus::Active,
        ];
    }

    public function released(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => HoldStatus::Released,
            'remaining_amount_minor' => 0,
            'released_at' => now(),
            'release_reason' => 'Demo release',
        ]);
    }
}

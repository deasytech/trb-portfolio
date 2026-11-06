<?php

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_number' => fake()->unique()->numerify('##########'),
            'account_type' => AccountType::Savings,
            'currency' => 'USD',
            'status' => AccountStatus::Active,
            'opened_at' => now(),
        ];
    }

    public function frozen(): static
    {
        return $this->state(['status' => AccountStatus::Frozen]);
    }
}

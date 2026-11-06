<?php

namespace Database\Factories;

use App\Enums\TransferStatus;
use App\Enums\TransferType;
use App\Models\Account;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Transfer>
 */
class TransferFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'TRF-'.Str::upper(Str::random(12)),
            'customer_id' => User::factory(),
            'source_account_id' => fn (array $attributes) => Account::factory()->create(['user_id' => $attributes['customer_id']])->id,
            'type' => TransferType::OutgoingRequest,
            'amount_minor' => fake()->numberBetween(100, 50_000),
            'currency' => 'USD',
            'description' => 'Demo transfer request',
            'status' => TransferStatus::Pending,
            'submitted_at' => now(),
        ];
    }

    public function completed(): static
    {
        return $this->state(['status' => TransferStatus::Completed, 'completed_at' => now()]);
    }
}

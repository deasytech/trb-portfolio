<?php

namespace Database\Factories;

use App\Enums\LedgerDirection;
use App\Enums\LedgerTransactionType;
use App\Models\Account;
use App\Models\LedgerTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LedgerTransaction>
 */
class LedgerTransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'reference' => 'TXN-'.Str::upper(Str::random(12)),
            'type' => LedgerTransactionType::Deposit,
            'direction' => LedgerDirection::Credit,
            'amount_minor' => fake()->numberBetween(100, 100_000),
            'currency' => 'USD',
            'description' => 'Demo ledger entry',
            'posted_at' => now(),
        ];
    }

    public function credit(int $amountMinor): static
    {
        return $this->state([
            'type' => LedgerTransactionType::Deposit,
            'direction' => LedgerDirection::Credit,
            'amount_minor' => $amountMinor,
        ]);
    }

    public function debit(int $amountMinor): static
    {
        return $this->state([
            'type' => LedgerTransactionType::Withdrawal,
            'direction' => LedgerDirection::Debit,
            'amount_minor' => $amountMinor,
        ]);
    }
}

<?php

namespace App\Services;

/**
 * Immutable snapshot of an account's balances, in minor units.
 */
final readonly class AccountBalance
{
    public function __construct(
        public int $ledgerMinor,
        public int $heldMinor,
        public string $currency,
    ) {}

    /**
     * Ledger balance minus active holds.
     */
    public function availableMinor(): int
    {
        return $this->ledgerMinor - $this->heldMinor;
    }
}

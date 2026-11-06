<?php

namespace App\Services;

use App\Enums\LedgerDirection;
use App\Models\Account;
use App\Models\FundsHold;
use App\Models\LedgerTransaction;
use LogicException;

/**
 * The single source of truth for balances.
 *
 * Ledger balance = posted credits - posted debits.
 * Available balance = ledger balance - remaining amount of active, unexpired holds.
 * Pending transfers are not posted to the ledger and are therefore ignored.
 */
class AccountBalanceCalculator
{
    public function forAccount(Account $account): AccountBalance
    {
        $this->assertSingleCurrency($account);

        $ledger = (int) LedgerTransaction::query()
            ->where('account_id', $account->id)
            ->selectRaw('COALESCE(SUM(CASE WHEN direction = ? THEN amount_minor ELSE -amount_minor END), 0) AS balance', [LedgerDirection::Credit->value])
            ->value('balance');

        $held = (int) FundsHold::query()
            ->where('account_id', $account->id)
            ->reserving()
            ->sum('remaining_amount_minor');

        return new AccountBalance($ledger, $held, $account->currency);
    }

    /**
     * Refuse to calculate when any row uses a currency other than the account's.
     */
    private function assertSingleCurrency(Account $account): void
    {
        $mismatch = LedgerTransaction::query()->where('account_id', $account->id)->where('currency', '!=', $account->currency)->exists()
            || FundsHold::query()->where('account_id', $account->id)->reserving()->where('currency', '!=', $account->currency)->exists();

        if ($mismatch) {
            throw new LogicException("Account {$account->id} has rows in a currency other than {$account->currency}.");
        }
    }
}

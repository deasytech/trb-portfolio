<?php

namespace App\Enums;

enum LedgerTransactionType: string
{
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Adjustment = 'adjustment';
    case Reversal = 'reversal';

    /**
     * Human-readable name.
     */
    public function label(): string
    {
        return match ($this) {
            self::Deposit => 'Deposit',
            self::Withdrawal => 'Withdrawal',
            self::TransferIn => 'Transfer In',
            self::TransferOut => 'Transfer Out',
            self::Adjustment => 'Adjustment',
            self::Reversal => 'Reversal',
        };
    }
}

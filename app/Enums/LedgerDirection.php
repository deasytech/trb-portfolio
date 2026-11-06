<?php

namespace App\Enums;

enum LedgerDirection: string
{
    case Debit = 'debit';
    case Credit = 'credit';

    /**
     * Human-readable name.
     */
    public function label(): string
    {
        return match ($this) {
            self::Debit => 'Debit',
            self::Credit => 'Credit',
        };
    }
}

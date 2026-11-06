<?php

namespace App\Enums;

enum AccountType: string
{
    case Savings = 'savings';
    case Checking = 'checking';

    /**
     * Human-readable name.
     */
    public function label(): string
    {
        return match ($this) {
            self::Savings => 'Savings',
            self::Checking => 'Checking',
        };
    }
}

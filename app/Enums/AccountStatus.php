<?php

namespace App\Enums;

enum AccountStatus: string
{
    case Active = 'active';
    case Frozen = 'frozen';
    case Closed = 'closed';

    /**
     * Human-readable name.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Frozen => 'Frozen',
            self::Closed => 'Closed',
        };
    }

    /**
     * Badge colour used by the status-badge component.
     */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Frozen => 'amber',
            self::Closed => 'zinc',
        };
    }
}

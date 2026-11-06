<?php

namespace App\Enums;

enum HoldStatus: string
{
    case Active = 'active';
    case Released = 'released';
    case Expired = 'expired';

    /**
     * Human-readable name.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Released => 'Released',
            self::Expired => 'Expired',
        };
    }

    /**
     * Badge colour used by the status-badge component.
     */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'amber',
            self::Released => 'green',
            self::Expired => 'zinc',
        };
    }
}

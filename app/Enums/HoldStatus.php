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
}

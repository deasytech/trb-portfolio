<?php

namespace App\Enums;

enum Theme: string
{
    case System = 'system';
    case Light = 'light';
    case Dark = 'dark';

    /**
     * Human-readable name.
     */
    public function label(): string
    {
        return match ($this) {
            self::System => 'System',
            self::Light => 'Light',
            self::Dark => 'Dark',
        };
    }
}

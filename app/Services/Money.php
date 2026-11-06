<?php

namespace App\Services;

/**
 * Formats integer minor units (two decimal places) for display.
 */
final class Money
{
    public static function format(int $amountMinor, string $currency): string
    {
        $sign = $amountMinor < 0 ? '-' : '';

        return $sign.$currency.' '.number_format(abs($amountMinor) / 100, 2);
    }
}

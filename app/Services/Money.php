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

    /**
     * Convert a validated decimal string such as "12.5" to minor units, or null when malformed.
     */
    public static function toMinor(string $amount): ?int
    {
        $amount = trim($amount);

        if (! preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/', $amount, $m)) {
            return null;
        }

        return ((int) $m[1]) * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    }
}

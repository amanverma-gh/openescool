<?php

namespace App\Support;

class Money
{
    /**
     * Format minor units (cents, paise) for emails, e.g. "USD 49.00".
     */
    public static function format(int $amount, string $currency): string
    {
        return strtoupper($currency).' '.number_format($amount / 100, 2);
    }

    /**
     * Convert a decimal amount typed by an admin ("9.99") to minor units.
     */
    public static function toMinor(float|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}

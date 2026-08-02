<?php

namespace App\Lib;

class Util
{
    /**
     * Renders a stored amount for display.
     *
     * Amounts are kept as integer minor units (cents), so this is the single
     * place to hang the currency symbol and decimal settings on later.
     */
    public static function displayAmount(int|float|string|null $amount): string
    {
        return number_format(((float) ($amount ?? 0)) / 100, 2, '.', ',');
    }

    /**
     * Converts a user typed amount ("12.50") into the stored minor units (1250).
     */
    public static function toMinorUnits(int|float|string|null $amount): int
    {
        return (int) round(((float) ($amount ?? 0)) * 100);
    }

    /**
     * Converts stored minor units back into a value suitable for a form field.
     */
    public static function toMajorUnits(int|float|string|null $amount): string
    {
        return number_format(((float) ($amount ?? 0)) / 100, 2, '.', '');
    }
}

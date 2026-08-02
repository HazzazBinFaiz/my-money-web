<?php

namespace App\Lib;

class Util
{
    /**
     * Renders a stored amount for display.
     *
     * Single place to hang currency symbol and decimal handling on later,
     * so every view goes through here instead of formatting inline.
     */
    public static function displayAmount(int|float|string|null $amount): string
    {
        return number_format((float) ($amount ?? 0), 0, '.', ',');
    }
}

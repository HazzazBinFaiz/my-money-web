<?php

namespace App\Lib;

use App\Enums\CurrencyPosition;
use App\Models\Book;
use App\Support\CurrentBook;

class Util
{
    /**
     * Renders a stored amount using the active book's currency settings.
     *
     * Amounts are stored as integer minor units (cents) regardless of the
     * chosen decimal places, which only control how they are shown.
     */
    public static function displayAmount(int|float|string|null $amount, ?Book $book = null): string
    {
        $book ??= app(CurrentBook::class)->get();

        $decimals = $book?->decimal_places ?? 2;
        $value = number_format(((float) ($amount ?? 0)) / 100, $decimals, '.', ',');

        $currency = trim((string) ($book?->currency ?? ''));

        if ($currency === '') {
            return $value;
        }

        return match ($book?->currency_position) {
            CurrencyPosition::After => $value.' '.$currency,
            CurrencyPosition::None => $value,
            default => $currency.$value,
        };
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

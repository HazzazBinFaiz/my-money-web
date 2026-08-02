<?php

namespace App\Enums;

enum CurrencyPosition: int
{
    case Before = 0;
    case After = 1;
    case None = 2;

    public function label(): string
    {
        return match ($this) {
            self::Before => 'Before amount',
            self::After => 'After amount',
            self::None => 'Hidden',
        };
    }
}

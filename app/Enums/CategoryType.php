<?php

namespace App\Enums;

enum CategoryType: int
{
    case Income = 0;
    case Expense = 1;

    public function label(): string
    {
        return match ($this) {
            self::Income => 'Income',
            self::Expense => 'Expense',
        };
    }
}

<?php

namespace App\Enums;

enum TransactionType: int
{
    case Income = 0;
    case Expense = 1;
    case Transfer = 2;

    public function label(): string
    {
        return match ($this) {
            self::Income => 'Income',
            self::Expense => 'Expense',
            self::Transfer => 'Transfer',
        };
    }
}

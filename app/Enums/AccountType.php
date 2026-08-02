<?php

namespace App\Enums;

enum AccountType: int
{
    case Account = 0;
    case Contact = 1;

    public function label(): string
    {
        return match ($this) {
            self::Account => 'Account',
            self::Contact => 'Contact',
        };
    }
}

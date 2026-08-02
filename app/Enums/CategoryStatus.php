<?php

namespace App\Enums;

enum CategoryStatus: int
{
    case Active = 0;
    case Inactive = 1;

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
        };
    }
}

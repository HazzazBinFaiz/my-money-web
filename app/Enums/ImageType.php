<?php

namespace App\Enums;

enum ImageType: int
{
    case Category = 0;
    case Account = 1;
    case Picture = 2;
    case Book = 3;

    public function label(): string
    {
        return match ($this) {
            self::Category => 'Category icon',
            self::Account => 'Account icon',
            self::Picture => 'Contact picture',
            self::Book => 'Book icon',
        };
    }
}

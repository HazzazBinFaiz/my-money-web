<?php

namespace App\Enums;

enum ImageType: int
{
    case Icon = 0;
    case Picture = 1;

    public function label(): string
    {
        return match ($this) {
            self::Icon => 'Icon',
            self::Picture => 'Picture',
        };
    }
}

<?php

namespace App\Lib;

class KeyContainer
{
    /** @var string 16 bytes */
    public string $aesKey;

    /** @var string 32 bytes */
    public string $hmacKey;
}

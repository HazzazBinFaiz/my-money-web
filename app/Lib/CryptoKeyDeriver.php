<?php

namespace App\Lib;

use RuntimeException;

class CryptoKeyDeriver
{
    public static function deriveKeyAndHmac(string $password = 'my_money_password', string $base64Salt = 'my_money_salt'): KeyContainer
    {
        // Base64 decode (Java: Base64.decode(str2, 2))
        $salt = base64_decode(str_replace('_', '', $base64Salt), true);
        if ($salt === false) {
            throw new RuntimeException('Invalid base64 salt');
        }

        // PBKDF2WithHmacSHA1
        // 384 bits = 48 bytes
        $derived = hash_pbkdf2(
            'sha1',          // HmacSHA1
            $password,
            $salt,
            10000,           // iterations
            48,              // output length in BYTES
            true             // raw output
        );

        if (strlen($derived) !== 48) {
            throw new RuntimeException('Key derivation failed');
        }

        // Split keys exactly like Java
        $aesKey = substr($derived, 0, 16);   // 16 bytes
        $hmacKey = substr($derived, 16, 32);  // 32 bytes

        $container = new KeyContainer;
        $container->aesKey = $aesKey;
        $container->hmacKey = $hmacKey;

        return $container;
    }
}

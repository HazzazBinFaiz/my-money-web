<?php

namespace App\Lib;

use Random\RandomException;
use RuntimeException;

class CryptoEncryptor
{
    /**
     * Encrypt plaintext and return iv:mac:ciphertext (Base64)
     *
     * @throws RuntimeException
     * @throws RandomException
     */
    public static function encrypt(
        string $plaintext,
        KeyContainer $keys
    ): string {
        // Java Cipher.getInstance("AES/CBC/PKCS5Padding")
        $ivLength = 16; // AES block size
        $iv = random_bytes($ivLength);

        $ciphertext = openssl_encrypt(
            $plaintext,
            'AES-128-CBC',
            $keys->aesKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($ciphertext === false) {
            throw new RuntimeException('AES encryption failed');
        }

        // MAC = HmacSHA256(iv || ciphertext)
        $macData = $iv.$ciphertext;
        $mac = hash_hmac(
            'sha256',
            $macData,
            $keys->hmacKey,
            true // raw
        );

        // Base64(iv):Base64(mac):Base64(ciphertext)
        return implode(':', [
            base64_encode($iv),
            base64_encode($mac),
            base64_encode($ciphertext),
        ]);
    }
}

<?php

namespace App\Lib;

use RuntimeException;

class CryptoRestore
{
    /**
     * @throws RuntimeException
     */
    public static function restore(
        EncryptedFileComponentContainer $enc,
        KeyContainer $keys
    ): string {
        // iv || ciphertext
        $dataToMac = $enc->iv.$enc->ciphertext;

        // HMAC-SHA256
        $computedMac = hash_hmac(
            'sha256',
            $dataToMac,
            $keys->hmacKey,
            true // raw output
        );

        // Constant-time comparison (matches Java XOR loop)
        if (! hash_equals($computedMac, $enc->mac)) {
            throw new RuntimeException(
                'MAC stored in civ does not match computed MAC.'
            );
        }

        // AES-128-CBC / PKCS5Padding (PKCS5 == PKCS7 for AES)
        $plaintext = openssl_decrypt(
            $enc->ciphertext,
            'AES-128-CBC',
            $keys->aesKey,
            OPENSSL_RAW_DATA,
            $enc->iv
        );

        if ($plaintext === false) {
            throw new RuntimeException('AES decryption failed');
        }

        return $plaintext; // UTF-8 string
    }
}

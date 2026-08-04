<?php

namespace App\Lib;

use InvalidArgumentException;
use RuntimeException;

/**
 * Reads .mbak backup files exported by the mobile app.
 *
 * The file is one line of "base64(iv):base64(mac):base64(ciphertext)",
 * AES-128-CBC with an HMAC-SHA256 tag, keyed by PBKDF2 over constants baked
 * into the exporting app.
 */
class MbakFile
{
    /**
     * @return array{accounts: array, categories: array, records: array, budgets: array}
     *
     * @throws RuntimeException when the file is not a readable backup
     */
    public static function read(string $contents): array
    {
        $contents = trim($contents);

        if (! preg_match('/^[A-Za-z0-9\/+=]*:[A-Za-z0-9\/+=]*:[A-Za-z0-9\/+=]*$/', $contents)) {
            throw new RuntimeException('This does not look like a .mbak backup file.');
        }

        try {
            $plaintext = CryptoRestore::restore(
                new EncryptedFileComponentContainer($contents),
                CryptoKeyDeriver::deriveKeyAndHmac(),
            );
        } catch (InvalidArgumentException|RuntimeException $e) {
            throw new RuntimeException('The backup could not be decrypted: '.$e->getMessage());
        }

        $decoded = json_decode($plaintext, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('The backup decrypted but did not contain readable data.');
        }

        return [
            'accounts' => $decoded['accounts'] ?? [],
            'categories' => $decoded['categories'] ?? [],
            'records' => $decoded['records'] ?? [],
            'budgets' => $decoded['budgets'] ?? [],
        ];
    }
}

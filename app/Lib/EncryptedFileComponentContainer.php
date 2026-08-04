<?php

namespace App\Lib;

use InvalidArgumentException;

class EncryptedFileComponentContainer implements \Stringable
{
    /** @var string raw bytes */
    public string $iv;

    /** @var string raw bytes */
    public string $mac;

    /** @var string raw bytes */
    public string $ciphertext;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(string $input)
    {
        $parts = explode(':', $input);
        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Cannot parse iv:mac:ciphertext');
        }

        $this->iv = base64_decode($parts[0], true);
        $this->mac = base64_decode($parts[1], true);
        $this->ciphertext = base64_decode($parts[2], true);

        if ($this->iv === false || $this->mac === false || $this->ciphertext === false) {
            throw new InvalidArgumentException('Invalid base64 encoding');
        }
    }

    public function __toString(): string
    {
        return base64_encode($this->iv).':'.base64_encode($this->mac).':'.base64_encode($this->ciphertext);
    }
}

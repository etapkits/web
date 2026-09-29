<?php

namespace App\Services;

use InvalidArgumentException;

class EmergencyPin
{
    public static function hash(string $pin): string
    {
        if (! preg_match('/^\d{4,8}$/', $pin)) {
            throw new InvalidArgumentException('Acil parola 4 ile 8 rakam olmalı.');
        }

        $rounds = 100_000;
        $salt = random_bytes(16);
        $digest = hash_pbkdf2('sha256', $pin, $salt, $rounds, 32, true);

        return 'pbkdf2_sha256$'.$rounds.'$'.bin2hex($salt).'$'.bin2hex($digest);
    }
}

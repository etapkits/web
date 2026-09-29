<?php

namespace App\Support;

class PhoneNumber
{
    public static function normalize(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw) ?? '';

        if (strlen($digits) === 12 && str_starts_with($digits, '90')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) !== 10 || ! str_starts_with($digits, '5')) {
            return null;
        }

        return '90'.$digits;
    }

    public static function digits(?string $raw): string
    {
        return preg_replace('/\D+/', '', (string) $raw) ?? '';
    }

    public static function e164(?string $raw): ?string
    {
        $normalized = self::normalize($raw);

        return $normalized === null ? null : '+'.$normalized;
    }

    public static function e164OrRaw(?string $raw): ?string
    {
        $e164 = self::e164($raw);

        if ($e164 !== null) {
            return $e164;
        }

        $digits = self::digits($raw);

        return $digits === '' ? null : '+'.$digits;
    }

    /**
     * Digits to match against stored "905xxxxxxxxx" values, or null when the keyword has no digits.
     */
    public static function searchFragment(string $keyword): ?string
    {
        $digits = self::digits($keyword);

        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return $digits === '' ? null : $digits;
    }

    public static function display(string $phone): string
    {
        $normalized = self::normalize($phone) ?? $phone;

        if (! preg_match('/^90(5\d{9})$/', $normalized, $matches)) {
            return $phone;
        }

        $local = $matches[1];

        return '0'.substr($local, 0, 3).' '.substr($local, 3, 3).' '.substr($local, 6, 2).' '.substr($local, 8, 2);
    }
}

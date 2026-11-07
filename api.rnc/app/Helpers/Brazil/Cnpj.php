<?php

namespace App\Helpers\Brazil;

class Cnpj
{
    private const LENGTH = 14;

    private const FIRST_WEIGHTS = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    private const SECOND_WEIGHTS = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    /**
     * Reduce a CNPJ input to its 14 digits so "12.345.678/0001-95" and
     * "12345678000195" store and compare identically. Returns null when there
     * are no digits.
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return $digits === '' ? null : $digits;
    }

    /**
     * Validate a CNPJ by its two check digits, rejecting repeated-digit sequences.
     */
    public static function isValid(?string $value): bool
    {
        $digits = self::normalize($value);

        if ($digits === null || strlen($digits) !== self::LENGTH) {
            return false;
        }

        if (preg_match('/^(\d)\1{13}$/', $digits) === 1) {
            return false;
        }

        if (self::checkDigit($digits, self::FIRST_WEIGHTS) !== (int) $digits[12]) {
            return false;
        }

        return self::checkDigit($digits, self::SECOND_WEIGHTS) === (int) $digits[13];
    }

    /**
     * @param  array<int, int>  $weights
     */
    private static function checkDigit(string $digits, array $weights): int
    {
        $sum = 0;

        foreach ($weights as $index => $weight) {
            $sum += (int) $digits[$index] * $weight;
        }

        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }
}

<?php

namespace App\Helpers\Brazil;

class Cpf
{
    private const LENGTH = 11;

    /**
     * Reduce a CPF input to its 11 digits so "123.456.789-09" and "12345678909"
     * store and compare identically. Returns null when there are no digits.
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
     * Validate a CPF by its two check digits, rejecting the known repeated-digit
     * sequences (e.g. "111.111.111-11") that pass the checksum but are invalid.
     */
    public static function isValid(?string $value): bool
    {
        $digits = self::normalize($value);

        if ($digits === null || strlen($digits) !== self::LENGTH) {
            return false;
        }

        if (preg_match('/^(\d)\1{10}$/', $digits) === 1) {
            return false;
        }

        for ($position = 9; $position < 11; $position++) {
            $sum = 0;

            for ($index = 0; $index < $position; $index++) {
                $sum += (int) $digits[$index] * (($position + 1) - $index);
            }

            $checkDigit = ((10 * $sum) % 11) % 10;

            if ($checkDigit !== (int) $digits[$position]) {
                return false;
            }
        }

        return true;
    }
}

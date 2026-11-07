<?php

namespace App\Helpers\Brazil;

class PhoneNumber
{
    private const COUNTRY_CODE = '55';

    private const NATIONAL_MIN_DIGITS = 10; // area code (2) + 8-digit landline

    private const NATIONAL_MAX_DIGITS = 11; // area code (2) + 9-digit mobile

    /**
     * Reduce a phone input to a canonical digits-only form with the country code, so
     * "(11) 99999-8888", "11999998888" and "+55 11 99999-8888" all store and compare as
     * "5511999998888". Returns null when there are no digits, letting a "required" rule
     * surface the error.
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if ($digits === '') {
            return null;
        }

        $isNationalNumber = strlen($digits) >= self::NATIONAL_MIN_DIGITS
            && strlen($digits) <= self::NATIONAL_MAX_DIGITS;

        if ($isNationalNumber) {
            return self::COUNTRY_CODE.$digits;
        }

        return $digits;
    }
}

<?php

namespace App\Modules\Fiscal\Helpers;

use App\Helpers\Brazil\Cnpj;

/**
 * The NF-e access key (rnc-2 R16; `facts.md` §5, marked to confirm): 44 digits —
 * cUF(2) AAMM(4) CNPJ(14) mod(2) série(3) nNF(9) tpEmis(1) cNF(8) cDV(1). The check digit is a mod-11
 * over the first 43 with weights 2–9 from the right, cycling; a remainder of 0 or 1 gives 0.
 */
final class AccessKey
{
    public const LENGTH = 44;

    private const PATTERN = '/^\d{44}$/';

    private const WEIGHTS = [2, 3, 4, 5, 6, 7, 8, 9];

    private const MODULUS = 11;

    private const EMITTER_OFFSET = 6;

    private const EMITTER_LENGTH = 14;

    private const MODEL_OFFSET = 20;

    private const MODEL_LENGTH = 2;

    /** True for 44 digits whose check digit holds and whose emitter is a valid CNPJ. */
    public static function isValid(string $key): bool
    {
        if (preg_match(self::PATTERN, $key) !== 1) {
            return false;
        }

        if (self::checkDigit(substr($key, 0, self::LENGTH - 1)) !== (int) $key[self::LENGTH - 1]) {
            return false;
        }

        return Cnpj::isValid(self::emitterCnpj($key));
    }

    /** @param  string  $first43  the key without its last digit */
    public static function checkDigit(string $first43): int
    {
        $sum = 0;

        foreach (array_reverse(str_split($first43)) as $position => $digit) {
            $sum += (int) $digit * self::WEIGHTS[$position % count(self::WEIGHTS)];
        }

        $remainder = $sum % self::MODULUS;

        return $remainder < 2 ? 0 : self::MODULUS - $remainder;
    }

    public static function emitterCnpj(string $key): string
    {
        return substr($key, self::EMITTER_OFFSET, self::EMITTER_LENGTH);
    }

    public static function model(string $key): string
    {
        return substr($key, self::MODEL_OFFSET, self::MODEL_LENGTH);
    }
}

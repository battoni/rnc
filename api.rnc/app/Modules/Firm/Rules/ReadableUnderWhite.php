<?php

namespace App\Modules\Firm\Rules;

use App\Modules\Firm\Helpers\Contrast;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A primary colour white text reads on (rnc-3 D4, rnc-4 R11): ≥ 4,5:1, else `brand.lowContrast`.
 * Every write that sets a primary uses it — a sub-brand's edit and the console's firm look.
 */
class ReadableUnderWhite implements ValidationRule
{
    public const LOW_CONTRAST = 'brand.lowContrast';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (Contrast::whiteOn((string) $value) < Contrast::AA_MINIMUM) {
            $fail(self::LOW_CONTRAST);
        }
    }
}

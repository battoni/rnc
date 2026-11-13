<?php

namespace App\Modules\Firm\Requests;

use App\Modules\Firm\Helpers\Contrast;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A sub-brand's edit (`PATCH /api/brands/{id}`, rnc-3 R5): every field optional. Colours are
 * `#rrggbb`; white text on the primary must reach 4,5:1 (D4), else `brand.lowContrast`. The proof is
 * exactly four `{value ≤ 20, label ≤ 60}` points. The slug, the firm and `active` are not editable
 * here — the switch is its own route.
 */
class UpdateBrandRequest extends FormRequest
{
    public const NAME_MAX_LENGTH = 120;

    public const SHORT_NAME_MAX_LENGTH = 40;

    public const TAGLINE_MAX_LENGTH = 160;

    public const PROOF_POINTS = 4;

    public const PROOF_VALUE_MAX_LENGTH = 20;

    public const PROOF_LABEL_MAX_LENGTH = 60;

    private const COLOUR_PATTERN = '/^#[0-9a-fA-F]{6}$/';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:'.self::NAME_MAX_LENGTH],
            'short_name' => ['sometimes', 'required', 'string', 'max:'.self::SHORT_NAME_MAX_LENGTH],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:'.self::TAGLINE_MAX_LENGTH],
            'brand_primary' => ['sometimes', 'bail', 'required', 'string', 'regex:'.self::COLOUR_PATTERN, $this->readableUnderWhite()],
            'brand_accent' => ['sometimes', 'required', 'string', 'regex:'.self::COLOUR_PATTERN],
            'proof' => ['sometimes', 'required', 'array:'.implode(',', range(0, self::PROOF_POINTS - 1)), 'size:'.self::PROOF_POINTS],
            'proof.*' => ['required', 'array:value,label'],
            'proof.*.value' => ['required', 'string', 'max:'.self::PROOF_VALUE_MAX_LENGTH],
            'proof.*.label' => ['required', 'string', 'max:'.self::PROOF_LABEL_MAX_LENGTH],
        ];
    }

    public function messages(): array
    {
        return [
            'brand_primary.regex' => 'brands.invalidColour',
            'brand_accent.regex' => 'brands.invalidColour',
        ];
    }

    /** Colours are stored lower-case, so a re-sent `#3B2A86` is the same colour. */
    protected function prepareForValidation(): void
    {
        foreach (['brand_primary', 'brand_accent'] as $field) {
            $colour = $this->input($field);

            if (is_string($colour)) {
                $this->merge([$field => strtolower(trim($colour))]);
            }
        }
    }

    private function readableUnderWhite(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (Contrast::whiteOn((string) $value) < Contrast::AA_MINIMUM) {
                $fail('brand.lowContrast');
            }
        };
    }
}

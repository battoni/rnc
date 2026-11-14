<?php

namespace App\Modules\Platform\Requests;

use App\Modules\Firm\Requests\UpdateBrandRequest;
use App\Modules\Firm\Rules\ReadableUnderWhite;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A firm's look from the console (`PATCH /api/platform/firms/{id}`, rnc-4 R25, R11): every field
 * optional, the same limits as a sub-brand's (`UpdateBrandRequest`). Colours are `#rrggbb`, stored
 * lower-case; white text on the primary must reach 4,5:1 (`brand.lowContrast`). The proof is
 * exactly four points, or null to show none. The slug, the platform flag and the modules are not
 * fields here — whatever else arrives is dropped by the DTO.
 */
class UpdatePlatformFirmRequest extends FormRequest
{
    public const LOGO_TEXT_MAX_LENGTH = 60;

    private const COLOUR_FIELDS = ['brand_primary', 'brand_accent'];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:'.UpdateBrandRequest::NAME_MAX_LENGTH],
            'logo_text' => ['sometimes', 'required', 'string', 'max:'.self::LOGO_TEXT_MAX_LENGTH],
            'short_name' => ['sometimes', 'required', 'string', 'max:'.UpdateBrandRequest::SHORT_NAME_MAX_LENGTH],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:'.UpdateBrandRequest::TAGLINE_MAX_LENGTH],
            'brand_primary' => ['sometimes', 'bail', 'required', 'string', 'regex:'.UpdateBrandRequest::COLOUR_PATTERN, new ReadableUnderWhite],
            'brand_accent' => ['sometimes', 'required', 'string', 'regex:'.UpdateBrandRequest::COLOUR_PATTERN],
            'proof' => ['sometimes', 'nullable', 'array:'.implode(',', range(0, UpdateBrandRequest::PROOF_POINTS - 1)), 'size:'.UpdateBrandRequest::PROOF_POINTS],
            'proof.*' => ['required', 'array:value,label'],
            'proof.*.value' => ['required', 'string', 'max:'.UpdateBrandRequest::PROOF_VALUE_MAX_LENGTH],
            'proof.*.label' => ['required', 'string', 'max:'.UpdateBrandRequest::PROOF_LABEL_MAX_LENGTH],
        ];
    }

    /** @return array<string, string> */
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
        foreach (self::COLOUR_FIELDS as $field) {
            $colour = $this->input($field);

            if (is_string($colour)) {
                $this->merge([$field => strtolower(trim($colour))]);
            }
        }
    }
}

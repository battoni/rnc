<?php

namespace App\Modules\Lead\Requests;

use App\Modules\Lead\Helpers\ConsentTerms;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A firm's new consent text (`POST /api/firm/consent-versions`, rnc-4 R33): the words of version n+1.
 * They must name the firm through `{escritorio}` — a consent that names nobody authorises nobody,
 * the same rule as the platform default (spec C15) — else `leads.consentPlaceholderMissing`.
 */
class StoreConsentVersionRequest extends FormRequest
{
    public const TEXT_MAX_LENGTH = 1000;

    public const PLACEHOLDER_MISSING = 'leads.consentPlaceholderMissing';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'text' => ['bail', 'required', 'string', 'max:'.self::TEXT_MAX_LENGTH, $this->namesTheFirm()],
        ];
    }

    protected function prepareForValidation(): void
    {
        $text = $this->input('text');

        if (is_string($text)) {
            $this->merge(['text' => trim($text)]);
        }
    }

    private function namesTheFirm(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! str_contains((string) $value, ConsentTerms::PLACEHOLDER)) {
                $fail(self::PLACEHOLDER_MISSING);
            }
        };
    }
}

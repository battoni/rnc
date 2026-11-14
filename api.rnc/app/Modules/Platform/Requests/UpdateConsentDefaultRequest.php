<?php

namespace App\Modules\Platform\Requests;

use App\Modules\Lead\Helpers\ConsentTerms;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The platform's default consent sentence (`PUT /api/platform/consent-default`, rnc-4 R13, R25):
 * what the next firm's version 1 copies. It must name the firm through `{escritorio}` — a consent
 * that names nobody authorises nobody — else `platform.consentPlaceholderMissing`.
 */
class UpdateConsentDefaultRequest extends FormRequest
{
    public const TEXT_MAX_LENGTH = 1000;

    public const PLACEHOLDER_MISSING = 'platform.consentPlaceholderMissing';

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

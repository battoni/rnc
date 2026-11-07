<?php

namespace App\Modules\Diagnostic\Requests;

use App\Helpers\Brazil\Cnpj;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The CNPJ path's lookup. The check digits are validated HERE, before anything calls BrasilAPI
 * (plan AM10): a typo is a 422 on the field, never a request to the registry.
 */
class LookupCnpjRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cnpj' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) {
                if (! Cnpj::isValid((string) $value)) {
                    $fail('diagnostic.cnpj.invalid');
                }
            }],
        ];
    }

    /** "11.222.333/0001-81" and "11222333000181" are one CNPJ: the rule and the row see the digits. */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('cnpj')) {
            return;
        }

        $this->merge(['cnpj' => Cnpj::normalize((string) $this->input('cnpj'))]);
    }
}

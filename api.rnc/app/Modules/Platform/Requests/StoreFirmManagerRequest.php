<?php

namespace App\Modules\Platform\Requests;

use App\Modules\Firm\Services\FirmProvisioner;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The console's manager login (`POST /api/platform/firms/{id}/manager`, rnc-4 R25): the username to
 * reset or create, and the new account's e-mail. Whose username it may be is
 * `FirmProvisioner::provideManager`'s rule.
 */
class StoreFirmManagerRequest extends FormRequest
{
    private const LOWER_CASE_FIELDS = ['username', 'email'];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'email' => ['nullable', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['username.required' => FirmProvisioner::MANAGER_REQUIRED];
    }

    protected function prepareForValidation(): void
    {
        foreach (self::LOWER_CASE_FIELDS as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $this->merge([$field => mb_strtolower(trim($value))]);
            }
        }
    }
}

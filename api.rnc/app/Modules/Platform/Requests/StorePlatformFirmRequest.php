<?php

namespace App\Modules\Platform\Requests;

use App\Modules\Firm\Requests\UpdateBrandRequest;
use App\Modules\Firm\Services\FirmProvisioner;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A new firm from the console (`POST /api/platform/firms`, rnc-4 R25): the same fields as
 * `firm:create`. Only presence and shape are checked here; the slug's format, reservation and
 * uniqueness, the manager's username and the e-mail are `FirmProvisioner`'s refusals (`firms.slug*`,
 * `users.form.*`), one set of rules for the console and the command line.
 */
class StorePlatformFirmRequest extends FormRequest
{
    private const LOWER_CASE_FIELDS = ['slug', 'manager', 'email'];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'slug' => ['required', 'string'],
            'name' => ['required', 'string', 'max:'.UpdateBrandRequest::NAME_MAX_LENGTH],
            'manager' => ['required', 'string'],
            'email' => ['nullable', 'string'],
            'specialist' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * A missing field answers the provisioner's own key, so the console reads one vocabulary.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.required' => FirmProvisioner::SLUG_INVALID,
            'name.required' => FirmProvisioner::NAME_REQUIRED,
            'manager.required' => FirmProvisioner::MANAGER_REQUIRED,
        ];
    }

    /** The slug and the username are compared as typed in lower case; the e-mail too (AM2). */
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

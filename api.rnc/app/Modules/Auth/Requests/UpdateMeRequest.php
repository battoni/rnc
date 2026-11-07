<?php

namespace App\Modules\Auth\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * What a person may change about themselves, and nothing else.
 *
 * The list is short on purpose. Role, status and permissions are decisions somebody else makes
 * about this account, so they stay in `UpdateUserRequest` behind `users.update`. The e-mail is not
 * here either: it carries verification (`email_verified_at`), so changing it is a flow — send a
 * code, confirm it — rather than a field, and a `PATCH` that quietly moved it would leave an
 * account verified against an address nobody proved.
 */
class UpdateMeRequest extends FormRequest
{
    private const NAME_MAX = 255;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:'.self::NAME_MAX],
            'last_name' => ['sometimes', 'required', 'string', 'max:'.self::NAME_MAX],
        ];
    }
}

<?php

namespace App\Modules\Partner\Requests;

use App\Modules\Auth\Repositories\UserRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A partner's portal login (`POST /api/partners/{id}/access`, R2): username and e-mail, unique
 * across `users` like any account the bootstrap creates (`StoreUserRequest`). The partner's own
 * closed login is ignored, so a regrant may keep the username it had.
 */
class CreatePartnerAccessRequest extends FormRequest
{
    public const USERNAME_MAX_LENGTH = 60;

    public const EMAIL_MAX_LENGTH = 160;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ownLoginId = app(UserRepository::class)->findUserByPartnerId((int) $this->route('id'))?->id;

        return [
            'username' => ['required', 'string', 'max:'.self::USERNAME_MAX_LENGTH, Rule::unique('users', 'username')->ignore($ownLoginId)],
            'email' => ['required', 'string', 'email', 'max:'.self::EMAIL_MAX_LENGTH, Rule::unique('users', 'email')->ignore($ownLoginId)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => is_string($this->input('username')) ? mb_strtolower(trim($this->input('username'))) : $this->input('username'),
            'email' => is_string($this->input('email')) ? mb_strtolower(trim($this->input('email'))) : $this->input('email'),
        ]);
    }
}

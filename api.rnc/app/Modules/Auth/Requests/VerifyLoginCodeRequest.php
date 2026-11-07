<?php

namespace App\Modules\Auth\Requests;

use App\Helpers\Brazil\PhoneNumber;
use App\Modules\Auth\Services\LoginCodeService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /api/auth/phone/verify`. Same normalisation as the request step, so the two calls always
 * agree on `login_codes.phone`.
 */
class VerifyLoginCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'phone' => [
                'required',
                'string',
                'digits_between:'.RequestLoginCodeRequest::PHONE_MIN_DIGITS.','.RequestLoginCodeRequest::PHONE_MAX_DIGITS,
            ],
            'code' => ['required', 'string', 'digits:'.LoginCodeService::CODE_LENGTH],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $this->merge(['phone' => PhoneNumber::normalize((string) $this->input('phone'))]);
        }
    }
}

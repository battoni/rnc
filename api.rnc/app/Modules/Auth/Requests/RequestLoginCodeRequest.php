<?php

namespace App\Modules\Auth\Requests;

use App\Helpers\Brazil\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /api/auth/phone/code`. The typed phone is normalised BEFORE validation, so the rules, the
 * stored row and the lookup all see `5511999998888`, never `(11) 99999-8888`.
 */
class RequestLoginCodeRequest extends FormRequest
{
    /** Country code + national number. A Brazilian landline is 12 digits; E.164 caps at 15. */
    public const PHONE_MIN_DIGITS = 12;

    public const PHONE_MAX_DIGITS = 15;

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
            'phone' => ['required', 'string', 'digits_between:'.self::PHONE_MIN_DIGITS.','.self::PHONE_MAX_DIGITS],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $this->merge(['phone' => PhoneNumber::normalize((string) $this->input('phone'))]);
        }
    }
}

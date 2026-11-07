<?php

namespace App\Modules\Partner\Requests;

use App\Modules\Partner\Enums\PartnerSpecialty;
use App\Modules\Partner\Helpers\PartnerInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A new partner (`POST /api/partners`, R21). The e-mail is where referrals are sent. */
class StorePartnerRequest extends FormRequest
{
    public const NAME_MAX_LENGTH = 120;

    public const EMAIL_MAX_LENGTH = 160;

    public const PHONE_MAX_LENGTH = 20;

    public const CITY_MAX_LENGTH = 80;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.self::NAME_MAX_LENGTH],
            'specialty' => ['required', 'string', Rule::in(PartnerSpecialty::values())],
            'email' => ['required', 'string', 'email', 'max:'.self::EMAIL_MAX_LENGTH],
            'phone' => ['nullable', 'string', 'max:'.self::PHONE_MAX_LENGTH],
            'city' => ['nullable', 'string', 'max:'.self::CITY_MAX_LENGTH],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(PartnerInput::normalize($this->all()));
    }
}

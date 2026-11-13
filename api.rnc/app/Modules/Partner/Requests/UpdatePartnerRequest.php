<?php

namespace App\Modules\Partner\Requests;

use App\Modules\Partner\Enums\PartnerSpecialty;
use App\Modules\Partner\Helpers\PartnerInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** An edit or a deactivation (`PATCH /api/partners/{id}`, R21): every field optional. */
class UpdatePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:'.StorePartnerRequest::NAME_MAX_LENGTH],
            'specialty' => ['sometimes', 'required', 'string', Rule::in(PartnerSpecialty::values())],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:'.StorePartnerRequest::EMAIL_MAX_LENGTH],
            'phone' => ['sometimes', 'nullable', 'string', 'max:'.StorePartnerRequest::PHONE_MAX_LENGTH],
            'city' => ['sometimes', 'nullable', 'string', 'max:'.StorePartnerRequest::CITY_MAX_LENGTH],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(PartnerInput::normalize($this->all()));
    }
}

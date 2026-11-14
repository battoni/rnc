<?php

namespace App\Modules\Lead\Requests;

use App\Helpers\Brazil\PhoneNumber;
use App\Modules\Lead\Enums\BetaClientsBand;
use App\Modules\Tracking\Enums\TrafficSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A firm's beta signup on the root site (`POST /api/public/beta-signups`, rnc-4 R29): the firm,
 * who is writing and how to reach them, where the firm is, its clients band, an optional message
 * and the LGPD consent, which must be given. The visit's source rides along as on the diagnostic.
 * Phone, e-mail and UF are normalised before the rules read them.
 */
class BetaSignupRequest extends FormRequest
{
    private const TEXT_MAX_LENGTH = 255;

    private const CITY_MAX_LENGTH = 120;

    private const MESSAGE_MAX_LENGTH = 1000;

    private const MEDIUM_MAX_LENGTH = 60;

    /** A Brazilian number after `PhoneNumber::normalize`: 55, the area code and 8 or 9 digits. */
    private const PHONE_PATTERN = '/^55\d{10,11}$/';

    /** The 27 federative units. */
    private const UFS = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company' => ['required', 'string', 'max:'.self::TEXT_MAX_LENGTH],
            'name' => ['required', 'string', 'max:'.self::TEXT_MAX_LENGTH],
            'role' => ['required', 'string', 'max:'.self::TEXT_MAX_LENGTH],
            'email' => ['required', 'string', 'email', 'max:'.self::TEXT_MAX_LENGTH],
            'phone' => ['required', 'string', 'regex:'.self::PHONE_PATTERN],
            'city' => ['required', 'string', 'max:'.self::CITY_MAX_LENGTH],
            'uf' => ['required', 'string', Rule::in(self::UFS)],
            'clients_band' => ['required', 'string', Rule::enum(BetaClientsBand::class)],
            'message' => ['nullable', 'string', 'max:'.self::MESSAGE_MAX_LENGTH],
            'consent' => ['required', 'accepted'],
            'source' => ['sometimes', 'string', Rule::enum(TrafficSource::class)],
            'medium' => ['nullable', 'string', 'max:'.self::MEDIUM_MAX_LENGTH],
        ];
    }

    /** "(31) 98888-7777" and "+55 31 98888-7777" are one number; an e-mail's case and a UF's are not part of it. */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        if (is_string($this->input('phone')) && $this->input('phone') !== '') {
            $normalized['phone'] = PhoneNumber::normalize($this->input('phone'));
        }

        if (is_string($this->input('email'))) {
            $normalized['email'] = mb_strtolower(trim($this->input('email')));
        }

        if (is_string($this->input('uf'))) {
            $normalized['uf'] = mb_strtoupper(trim($this->input('uf')));
        }

        $this->merge($normalized);
    }
}

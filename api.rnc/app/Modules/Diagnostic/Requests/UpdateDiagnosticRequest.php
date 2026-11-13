<?php

namespace App\Modules\Diagnostic\Requests;

use App\Helpers\Brazil\PhoneNumber;
use App\Modules\Diagnostic\Enums\Regime;
use App\Modules\Diagnostic\Enums\RevenueBand;
use App\Modules\Diagnostic\Enums\Sector;
use App\Modules\Lead\Enums\LeadStep;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A later step (`PATCH /api/public/diagnostics/{token}`): the `contact` right after step 1, the
 * `profile` answers of the quiz (any subset — merged over the stored ones), and the `step` the
 * visitor is now on. Phone and e-mail are normalised before the rules read them.
 */
class UpdateDiagnosticRequest extends FormRequest
{
    private const TEXT_MAX_LENGTH = 255;

    /** A Brazilian number after `PhoneNumber::normalize`: 55, the area code and 8 or 9 digits. */
    private const PHONE_PATTERN = '/^55\d{10,11}$/';

    private const B2B_MAX = 100;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contact' => ['sometimes', 'array:company,name,phone,email,consent'],
            'contact.company' => ['exclude_without:contact', 'required', 'string', 'max:'.self::TEXT_MAX_LENGTH],
            'contact.name' => ['exclude_without:contact', 'nullable', 'string', 'max:'.self::TEXT_MAX_LENGTH],
            'contact.phone' => ['exclude_without:contact', 'nullable', 'required_without:contact.email', 'string', 'regex:'.self::PHONE_PATTERN],
            'contact.email' => ['exclude_without:contact', 'nullable', 'required_without:contact.phone', 'string', 'email', 'max:'.self::TEXT_MAX_LENGTH],
            'contact.consent' => ['exclude_without:contact', 'required', 'boolean'],

            'profile' => ['sometimes', 'array:sector,regime,band,b2b,incentivos,exporta'],
            'profile.sector' => ['sometimes', 'nullable', 'string', Rule::enum(Sector::class)],
            'profile.regime' => ['sometimes', 'nullable', 'string', Rule::enum(Regime::class)],
            'profile.band' => ['sometimes', 'nullable', 'string', Rule::enum(RevenueBand::class)],
            'profile.b2b' => ['sometimes', 'nullable', 'integer', 'between:0,'.self::B2B_MAX],
            'profile.incentivos' => ['sometimes', 'boolean'],
            'profile.exporta' => ['sometimes', 'boolean'],

            'step' => ['sometimes', 'string', Rule::enum(LeadStep::class)],
        ];
    }

    /** "(11) 98888-7777" and "+55 11 98888-7777" are one number; an e-mail's case is not part of it. */
    protected function prepareForValidation(): void
    {
        $contact = $this->input('contact');

        if (! is_array($contact)) {
            return;
        }

        if (! empty($contact['phone'])) {
            $contact['phone'] = PhoneNumber::normalize((string) $contact['phone']);
        }

        if (! empty($contact['email'])) {
            $contact['email'] = mb_strtolower(trim((string) $contact['email']));
        }

        $this->merge(['contact' => $contact]);
    }
}

<?php

namespace App\Modules\Partner\Requests;

use App\Modules\Partner\Enums\ReferralStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A partner's move (rnc-2 R7): `to` is a status; `reuniao` needs the meeting's date-time, in the
 * future; `fechado` needs the first-year contract value in whole cents, above zero. Either field
 * sent with another move is dropped, not stored. Whether the move is on the graph is the service's
 * question (422 `referral.invalidTransition`), asked under the row's lock.
 */
class TransitionReferralRequest extends FormRequest
{
    /** R$ 1 bilhão — a typo guard, far above any first-year contract of the network. */
    public const DEAL_VALUE_MAX_CENTS = 100_000_000_000;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to' => ['required', 'string', Rule::enum(ReferralStatus::class)],
            'meeting_at' => ['exclude_unless:to,'.ReferralStatus::Reuniao->value, 'bail', 'required', 'date', 'after:now'],
            'deal_value_cents' => ['exclude_unless:to,'.ReferralStatus::Fechado->value, 'bail', 'required', 'integer', 'min:1', 'max:'.self::DEAL_VALUE_MAX_CENTS],
        ];
    }

    public function messages(): array
    {
        return [
            'meeting_at.required' => 'portal.referrals.meetingRequired',
            'meeting_at.date' => 'portal.referrals.meetingInvalid',
            'meeting_at.after' => 'portal.referrals.meetingInPast',
            'deal_value_cents.required' => 'portal.referrals.dealValueRequired',
            'deal_value_cents.integer' => 'portal.referrals.dealValueInvalid',
            'deal_value_cents.min' => 'portal.referrals.dealValueInvalid',
            'deal_value_cents.max' => 'portal.referrals.dealValueInvalid',
        ];
    }
}

<?php

namespace App\Modules\Partner\Requests;

use App\Modules\Firm\Services\CurrentFirm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A referral (`POST /api/leads/{id}/referrals`, R22): an ACTIVE partner of the person's own firm —
 * the `exists` asks whose it is (arcus "Rows a person owns") — and the message the partner reads.
 */
class ReferLeadRequest extends FormRequest
{
    public const MESSAGE_MAX_LENGTH = 1000;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'partner_id' => ['required', 'integer', Rule::exists('partners', 'id')->where('firm_id', app(CurrentFirm::class)->id())->where('active', true)],
            'message' => ['required', 'string', 'max:'.self::MESSAGE_MAX_LENGTH],
        ];
    }

    public function messages(): array
    {
        return ['partner_id.exists' => 'partners.routing.partnerUnavailable'];
    }

    protected function prepareForValidation(): void
    {
        $message = $this->input('message');

        if (! is_string($message)) {
            return;
        }

        $this->merge(['message' => trim($message)]);
    }
}

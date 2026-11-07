<?php

namespace App\Modules\Partner\Requests;

use App\Modules\Firm\Services\CurrentFirm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A legal referral (`POST /api/contracts/{id}/referrals`, rnc-3 R20): an ACTIVE partner of the
 * person's own firm — the `exists` asks whose it is (arcus "Rows a person owns"). Its specialty is
 * the service's rule (422 `partner_not_legal`), not a validation message.
 */
class ReferContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'partner_id' => ['required', 'integer', Rule::exists('partners', 'id')->where('firm_id', app(CurrentFirm::class)->id())->where('active', true)],
        ];
    }

    public function messages(): array
    {
        return ['partner_id.exists' => 'partners.routing.partnerUnavailable'];
    }
}

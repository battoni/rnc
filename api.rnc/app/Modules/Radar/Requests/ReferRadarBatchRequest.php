<?php

namespace App\Modules\Radar\Requests;

use App\Modules\Diagnostic\Enums\DiagnosticRule;
use App\Modules\Firm\Services\CurrentFirm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Encaminhar os N maiores" (`POST /api/radar/referrals`, rnc-2 R27): a rule of the engine and an
 * ACTIVE partner of the person's own firm — the `exists` asks whose it is (arcus "Rows a person
 * owns"); whether its specialty covers the rule is the service's 422 `partner_not_covering`.
 */
class ReferRadarBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rule' => ['required', 'string', Rule::enum(DiagnosticRule::class)],
            'partner_id' => ['required', 'integer', Rule::exists('partners', 'id')->where('firm_id', app(CurrentFirm::class)->id())->where('active', true)],
        ];
    }

    public function messages(): array
    {
        return ['partner_id.exists' => 'partners.routing.partnerUnavailable'];
    }
}

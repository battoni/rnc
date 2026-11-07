<?php

namespace App\Modules\Partner\Requests;

use App\Modules\Firm\Enums\PartnerMoneyModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The network's money model (`PATCH /api/ledger/model`, rnc-2 R11, D10): `comissao` or `assinatura`. */
class UpdateLedgerModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'model' => ['required', 'string', Rule::enum(PartnerMoneyModel::class)],
        ];
    }
}

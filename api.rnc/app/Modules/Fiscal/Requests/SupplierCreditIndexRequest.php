<?php

namespace App\Modules\Fiscal\Requests;

use App\Modules\Diagnostic\Enums\Sector;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The supplier-credit list's filter (`GET /api/fiscal/supplier-credit`, R22): a sector, optional. */
class SupplierCreditIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sector' => ['nullable', 'string', Rule::in(array_column(Sector::cases(), 'value'))],
        ];
    }
}

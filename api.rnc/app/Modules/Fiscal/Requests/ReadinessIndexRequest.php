<?php

namespace App\Modules\Fiscal\Requests;

use App\Modules\Fiscal\Enums\ReadinessStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The readiness list's filters (`GET /api/fiscal/readiness`, R19): a status and an emitting system, each optional. */
class ReadinessIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(array_column(ReadinessStatus::cases(), 'value'))],
            'system' => ['nullable', 'string', 'max:60'],
        ];
    }
}

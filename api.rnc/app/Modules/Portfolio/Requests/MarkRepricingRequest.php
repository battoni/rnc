<?php

namespace App\Modules\Portfolio\Requests;

use App\Modules\Firm\Services\CurrentFirm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The batch mark (`POST /api/repricing/mark`, R26): the ids of the chosen clients, each asked
 * against the current firm (arcus "An id that arrives in the request is checked against its owner").
 */
class MarkRepricingRequest extends FormRequest
{
    public const MAX_CLIENTS = 1000;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_CLIENTS],
            'client_ids.*' => ['required', 'integer', 'distinct', Rule::exists('clients', 'id')->where('firm_id', app(CurrentFirm::class)->id())],
        ];
    }
}

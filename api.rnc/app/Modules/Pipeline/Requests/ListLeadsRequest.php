<?php

namespace App\Modules\Pipeline\Requests;

use App\Modules\Lead\Enums\LeadStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The board's filters (`GET /api/leads`, R18): a search term and, optionally, one stage. */
class ListLeadsRequest extends FormRequest
{
    private const SEARCH_MAX_LENGTH = 100;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:'.self::SEARCH_MAX_LENGTH],
            'stage' => ['nullable', 'string', Rule::enum(LeadStage::class)],
        ];
    }
}

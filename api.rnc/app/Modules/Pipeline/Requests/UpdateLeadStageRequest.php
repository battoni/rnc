<?php

namespace App\Modules\Pipeline\Requests;

use App\Modules\Lead\Enums\LeadStage;
use App\Modules\Pipeline\Enums\MoveVia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A card's move (`PATCH /api/leads/{id}/stage`, R18): the stage, the reason when it is perdido, and
 * how it was moved. Whether the move is allowed from where the lead is now is the service's rule.
 */
class UpdateLeadStageRequest extends FormRequest
{
    public const REASON_MAX_LENGTH = 300;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stage' => ['required', 'string', Rule::enum(LeadStage::class)],
            'reason' => ['nullable', 'string', 'max:'.self::REASON_MAX_LENGTH, Rule::requiredIf(fn () => $this->input('stage') === LeadStage::Perdido->value)],
            'via' => ['sometimes', 'string', Rule::enum(MoveVia::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => 'pipeline.lostReasonRequired',
        ];
    }

    /** A reason of only spaces is no reason. */
    protected function prepareForValidation(): void
    {
        $reason = $this->input('reason');

        if (! is_string($reason)) {
            return;
        }

        $this->merge(['reason' => trim($reason) ?: null]);
    }
}

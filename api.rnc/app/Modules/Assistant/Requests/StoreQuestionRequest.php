<?php

namespace App\Modules\Assistant\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A hand-off (`POST /api/assistant/questions`, rnc-3 R13, AM7): the question, 3–300 characters, and
 * optionally the specialist picked. Whether that person is an active specialist of the firm is the
 * service's answer (422 `assistant.notSpecialist`), not an `exists` here.
 */
class StoreQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question' => AskRequest::questionRule(),
            'specialist_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('question'))) {
            $this->merge(['question' => trim($this->input('question'))]);
        }
    }
}

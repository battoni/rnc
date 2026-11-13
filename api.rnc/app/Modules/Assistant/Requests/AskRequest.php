<?php

namespace App\Modules\Assistant\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A question to the assistant (`POST /api/assistant/ask`, rnc-3 R12, AM7): 3–300 characters. A
 * suggestion chip also sends its `note_id`; a note of another firm, or no longer served, answers
 * nobody, so the id is not checked here.
 */
class AskRequest extends FormRequest
{
    public const QUESTION_MIN_LENGTH = 3;

    public const QUESTION_MAX_LENGTH = 300;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question' => self::questionRule(),
            'note_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    /** @return list<string> */
    public static function questionRule(): array
    {
        return ['required', 'string', 'min:'.self::QUESTION_MIN_LENGTH, 'max:'.self::QUESTION_MAX_LENGTH];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('question'))) {
            $this->merge(['question' => trim($this->input('question'))]);
        }
    }
}

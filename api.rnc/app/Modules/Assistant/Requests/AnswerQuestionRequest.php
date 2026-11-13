<?php

namespace App\Modules\Assistant\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A specialist's answer (`POST /api/assistant/questions/{id}/answer`, rnc-3 R14): 3–2000 characters
 * (the column's length), trimmed by the global `TrimStrings`. Whether the question is theirs is the service's (404).
 */
class AnswerQuestionRequest extends FormRequest
{
    public const ANSWER_MIN_LENGTH = 3;

    public const ANSWER_MAX_LENGTH = 2000;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'answer' => ['required', 'string', 'min:'.self::ANSWER_MIN_LENGTH, 'max:'.self::ANSWER_MAX_LENGTH],
        ];
    }
}

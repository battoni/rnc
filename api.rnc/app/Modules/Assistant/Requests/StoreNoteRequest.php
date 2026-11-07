<?php

namespace App\Modules\Assistant\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A new note (`POST /api/assistant/notes`, rnc-3 R10, AM7): question ≤ 200, answer ≤ 2000, rule
 * ≤ 200, source label ≤ 160 and an http(s) URL ≤ 300, `as_of` a day not in the future, at least one
 * strong key; each key 2–40 characters, at most 12 of each kind. `from_question` names the handed-off
 * question "Virar nota" turns into this note (F5); whether it is the writer's to turn is the service's.
 */
class StoreNoteRequest extends FormRequest
{
    public const QUESTION_MAX_LENGTH = 200;

    public const ANSWER_MAX_LENGTH = 2000;

    public const RULE_MAX_LENGTH = 200;

    public const SOURCE_LABEL_MAX_LENGTH = 160;

    public const SOURCE_URL_MAX_LENGTH = 300;

    public const KEYWORD_MIN_LENGTH = 2;

    public const KEYWORD_MAX_LENGTH = 40;

    public const KEYWORDS_MAX_COUNT = 12;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...self::contentRules(required: true),
            'from_question' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, list<string>> */
    public static function contentRules(bool $required): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'question' => [$presence, 'string', 'max:'.self::QUESTION_MAX_LENGTH],
            'answer' => [$presence, 'string', 'max:'.self::ANSWER_MAX_LENGTH],
            'rule' => [$presence, 'string', 'max:'.self::RULE_MAX_LENGTH],
            'source_label' => [$presence, 'string', 'max:'.self::SOURCE_LABEL_MAX_LENGTH],
            'source_url' => [$presence, 'string', 'url:http,https', 'max:'.self::SOURCE_URL_MAX_LENGTH],
            'as_of' => [$presence, 'date_format:Y-m-d', 'before_or_equal:today'],
            'keywords_strong' => [$presence, 'array', 'min:1', 'max:'.self::KEYWORDS_MAX_COUNT],
            'keywords_strong.*' => ['string', 'min:'.self::KEYWORD_MIN_LENGTH, 'max:'.self::KEYWORD_MAX_LENGTH],
            'keywords_weak' => [$required ? 'present' : 'sometimes', 'array', 'max:'.self::KEYWORDS_MAX_COUNT],
            'keywords_weak.*' => ['string', 'min:'.self::KEYWORD_MIN_LENGTH, 'max:'.self::KEYWORD_MAX_LENGTH],
        ];
    }
}

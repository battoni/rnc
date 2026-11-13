<?php

namespace App\Modules\Pipeline\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** A team note on a lead (`POST /api/leads/{id}/notes`, R19). */
class StoreLeadNoteRequest extends FormRequest
{
    private const TEXT_MAX_LENGTH = 2000;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:'.self::TEXT_MAX_LENGTH],
        ];
    }

    protected function prepareForValidation(): void
    {
        $text = $this->input('text');

        if (! is_string($text)) {
            return;
        }

        $this->merge(['text' => trim($text)]);
    }
}

<?php

namespace App\Modules\Assistant\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * An edit (`PATCH /api/assistant/notes/{id}`, rnc-3 R10, D7): any of the note's content fields,
 * held to `StoreNoteRequest`'s bounds. The status, the reviewer and the slug are not here: a note is
 * reviewed through `review` and retired through `retire`.
 */
class UpdateNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return StoreNoteRequest::contentRules(required: false);
    }
}

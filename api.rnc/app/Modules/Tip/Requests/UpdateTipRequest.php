<?php

namespace App\Modules\Tip\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'anchor_key' => ['sometimes', 'string', 'max:191', 'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/i'],
            'content' => ['sometimes', 'string', 'max:20000'],
            /*
             * Not `required` here: whether a reason is owed depends on whether the CONTENT
             * actually changed, which only the service can tell after sanitising. It raises the
             * same validation error from there.
             */
            'change_reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}

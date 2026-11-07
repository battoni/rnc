<?php

namespace App\Modules\Tip\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTipRequest extends FormRequest
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
            /*
             * The anchor catalogue lives in the frontend, next to the <ATip> call sites, so this
             * validates shape rather than membership: dotted lowercase segments. A key that
             * matches nothing simply never renders — it cannot break a screen.
             */
            'anchor_key' => ['required', 'string', 'max:191', 'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/i'],
            'content' => ['required', 'string', 'max:20000'],
            'change_reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}

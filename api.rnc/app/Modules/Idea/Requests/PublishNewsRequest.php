<?php

namespace App\Modules\Idea\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Publishing is its own verb, not a side effect of saving.
 *
 * The full update requires `title` and `body` and reads every other field as "set it to this" —
 * so a caller who only wanted to flip the switch would have to resend the whole article, and any
 * field it forgot (a subtitle, a link) would be wiped by the omission. One boolean cannot do that.
 */
class PublishNewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'publish' => ['required', 'boolean'],
        ];
    }
}

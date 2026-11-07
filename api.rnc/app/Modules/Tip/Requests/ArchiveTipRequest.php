<?php

namespace App\Modules\Tip\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArchiveTipRequest extends FormRequest
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
            // Taking a tip out of the app is exactly the change nobody remembers making later.
            'change_reason' => ['required', 'string', 'max:255'],
        ];
    }
}

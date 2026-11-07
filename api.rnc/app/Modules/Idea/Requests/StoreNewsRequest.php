<?php

namespace App\Modules\Idea\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNewsRequest extends FormRequest
{
    use NewsRequestRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->newsFieldRules() + [
            // Optional on CREATE, and absent means draft — the safe direction. Writing an entry
            // and announcing it are different moments, and a caller who says nothing has not
            // asked for an announcement.
            'publish' => ['nullable', 'boolean'],
        ];
    }
}

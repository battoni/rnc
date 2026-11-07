<?php

namespace App\Modules\Idea\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIdeaRequest extends FormRequest
{
    use IdeaRequestRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->ideaFieldRules() + [
            'removeAttachment' => ['nullable', 'boolean'],
        ];
    }
}

<?php

namespace App\Modules\Idea\Requests;

use App\Modules\Idea\Enums\IdeaStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIdeaStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(IdeaStatus::values())],
            'adminNote' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

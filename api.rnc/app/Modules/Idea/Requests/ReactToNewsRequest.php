<?php

namespace App\Modules\Idea\Requests;

use App\Modules\Idea\Enums\NewsReactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReactToNewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(NewsReactionType::values())],
        ];
    }
}

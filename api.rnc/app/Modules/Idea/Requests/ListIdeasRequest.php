<?php

namespace App\Modules\Idea\Requests;

use App\Modules\Idea\Enums\IdeaArea;
use App\Modules\Idea\Enums\IdeaStatus;
use App\Modules\Idea\Enums\IdeaType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListIdeasRequest extends FormRequest
{
    private const SORTS = ['votes', 'recent'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(IdeaStatus::values())],
            'type' => ['nullable', 'string', Rule::in(IdeaType::values())],
            'area' => ['nullable', 'string', Rule::in(IdeaArea::values())],
            'search' => ['nullable', 'string', 'max:120'],
            'mine' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string', Rule::in(self::SORTS)],
        ];
    }
}

<?php

namespace App\Modules\Pipeline\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The agenda's week (`GET /api/agenda?week=`, R20): any date of it; none is this week. */
class AgendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'week' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}

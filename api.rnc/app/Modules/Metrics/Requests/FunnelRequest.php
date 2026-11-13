<?php

namespace App\Modules\Metrics\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The funnel's window (`GET /api/metrics/funnel?days=`, R24): the last 7 or 30 days; none is 30. */
class FunnelRequest extends FormRequest
{
    public const WINDOWS = [7, 30];

    public const DEFAULT_DAYS = 30;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'days' => ['nullable', 'integer', 'in:'.implode(',', self::WINDOWS)],
        ];
    }
}

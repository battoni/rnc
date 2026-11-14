<?php

namespace App\Modules\Booking\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A specialist's whole week (`PUT /api/specialists/{id}/hours`, rnc-4 R32): a list of
 * `{weekday, opens_at, closes_at}` — ISO weekday (1 = segunda) and `HH:MM` local times, closing after
 * opening. An empty list empties the week; a day may hold two windows (a lunch break).
 */
class UpdateSpecialistHoursRequest extends FormRequest
{
    /** Three windows a day, every day, is more than any agenda needs. */
    public const MAX_WINDOWS = 21;

    private const TIME_FORMAT = 'date_format:H:i';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'hours' => ['present', 'array', 'max:'.self::MAX_WINDOWS],
            'hours.*.weekday' => ['required', 'integer', 'between:1,7'],
            'hours.*.opens_at' => ['required', 'string', self::TIME_FORMAT],
            'hours.*.closes_at' => ['required', 'string', self::TIME_FORMAT, 'after:hours.*.opens_at'],
        ];
    }
}

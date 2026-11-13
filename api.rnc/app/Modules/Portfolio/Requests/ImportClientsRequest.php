<?php

namespace App\Modules\Portfolio\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The portfolio CSV (`POST /api/clients/import`, R25): one file of up to 2 MB. */
class ImportClientsRequest extends FormRequest
{
    public const MAX_KILOBYTES = 2048;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:csv,txt', 'max:'.self::MAX_KILOBYTES],
        ];
    }
}

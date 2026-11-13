<?php

namespace App\Modules\Portfolio\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The contracts CSV (`POST /api/contracts/import`, rnc-3 R18): one .csv or .txt file of up to 2 MB. */
class ImportContractsRequest extends FormRequest
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

<?php

namespace App\Modules\Fiscal\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The NF-e upload (`POST /api/fiscal/imports`, R15): one `.xml` of up to 20 MB, the import's whole
 * ceiling (plan Defaults). Past that, the 1 MB per XML is the source's to enforce (`too_large`). F6b
 * adds `.zip`.
 */
class StoreNfeImportRequest extends FormRequest
{
    public const MAX_KILOBYTES = 20 * 1024;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:xml', 'max:'.self::MAX_KILOBYTES],
        ];
    }
}

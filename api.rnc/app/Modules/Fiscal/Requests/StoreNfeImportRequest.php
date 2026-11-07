<?php

namespace App\Modules\Fiscal\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The NF-e upload (`POST /api/fiscal/imports`, R15): one `.xml` or `.zip` of up to 20 MB, the
 * import's whole ceiling (plan Defaults). Past that, the 1 MB per XML, the 20 MB read out of a ZIP and
 * its 2000 entries are the source's to enforce (`too_large` in the batch, `UploadedNfeSource`).
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
            'file' => ['required', 'file', 'extensions:xml,zip', 'max:'.self::MAX_KILOBYTES],
        ];
    }
}

<?php

namespace App\Modules\Fiscal\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The import batches' page (`GET /api/fiscal/imports`, R17), newest first. */
class ListNfeImportsRequest extends FormRequest
{
    public const PER_PAGE_DEFAULT = 20;

    private const PER_PAGE_MAX = 100;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::PER_PAGE_MAX],
        ];
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? self::PER_PAGE_DEFAULT);
    }
}

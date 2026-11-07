<?php

namespace App\Modules\Pipeline\Requests;

use App\Modules\Firm\Services\BrandService;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Lead\Enums\LeadStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The board's filters (`GET /api/leads`, R18): a search term and, optionally, one stage and one
 * brand (rnc-3 R4, AM13) — `main` or a slug of the person's own firm, inactive too. Any other slug,
 * another firm's included, is 422 `brands.notFound`.
 */
class ListLeadsRequest extends FormRequest
{
    private const SEARCH_MAX_LENGTH = 100;

    private const BRAND_MAX_LENGTH = 40;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:'.self::SEARCH_MAX_LENGTH],
            'stage' => ['nullable', 'string', Rule::enum(LeadStage::class)],
            'brand' => [
                'nullable',
                'string',
                'max:'.self::BRAND_MAX_LENGTH,
                Rule::when(
                    $this->input('brand') !== BrandService::MAIN_SLUG,
                    [Rule::exists('firm_brands', 'slug')->where('firm_id', app(CurrentFirm::class)->id())],
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return ['brand.exists' => 'brands.notFound'];
    }
}

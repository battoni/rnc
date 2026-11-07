<?php

namespace App\Modules\Partner\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The ledger's month (`?month=YYYY-MM`, rnc-2 R11), on the portal's statement and on the network
 * ledger; none is this month in São Paulo. Years 2000–2099 only: anything else is a typo.
 */
class LedgerMonthRequest extends FormRequest
{
    public const MONTH_PATTERN = '/^20\d{2}-(0[1-9]|1[0-2])$/';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'month' => ['nullable', 'string', 'regex:'.self::MONTH_PATTERN],
        ];
    }
}

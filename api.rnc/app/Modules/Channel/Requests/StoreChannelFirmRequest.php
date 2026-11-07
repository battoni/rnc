<?php

namespace App\Modules\Channel\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A new channel firm (`POST /api/channel/firms`, rnc-3 R7, AM9). The fee is R$ 0,01–100.000,00 in
 * whole cents and the share 0–50 % in whole basis points, else 422. It always starts at contato:
 * a `stage` sent here is ignored.
 */
class StoreChannelFirmRequest extends FormRequest
{
    public const NAME_MAX_LENGTH = 120;

    public const CITY_MAX_LENGTH = 80;

    public const NOTES_MAX_LENGTH = 500;

    public const FEE_MIN_CENTS = 1;

    public const FEE_MAX_CENTS = 10_000_000;

    public const SHARE_MIN_BP = 0;

    public const SHARE_MAX_BP = 5000;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.self::NAME_MAX_LENGTH],
            'city' => ['required', 'string', 'max:'.self::CITY_MAX_LENGTH],
            'monthly_fee_cents' => ['sometimes', 'required', ...self::feeRule()],
            'share_bp' => ['sometimes', 'required', ...self::shareRule()],
            'notes' => ['sometimes', 'nullable', 'string', 'max:'.self::NOTES_MAX_LENGTH],
        ];
    }

    /** @return list<string> */
    public static function feeRule(): array
    {
        return ['integer', 'between:'.self::FEE_MIN_CENTS.','.self::FEE_MAX_CENTS];
    }

    /** @return list<string> */
    public static function shareRule(): array
    {
        return ['integer', 'between:'.self::SHARE_MIN_BP.','.self::SHARE_MAX_BP];
    }
}

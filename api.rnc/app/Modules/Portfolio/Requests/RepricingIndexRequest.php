<?php

namespace App\Modules\Portfolio\Requests;

use App\Modules\Diagnostic\Enums\Regime;
use App\Modules\Diagnostic\Enums\Sector;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The repricing list's filters (`GET /api/repricing`, R26): a sector and a client regime, each optional. */
class RepricingIndexRequest extends FormRequest
{
    /** A client's regime is known: "não sei" is the quiz's, never a client's. */
    public const REGIMES = [Regime::Simples->value, Regime::Presumido->value, Regime::Real->value];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sector' => ['nullable', 'string', Rule::in(array_column(Sector::cases(), 'value'))],
            'regime' => ['nullable', 'string', Rule::in(self::REGIMES)],
        ];
    }
}

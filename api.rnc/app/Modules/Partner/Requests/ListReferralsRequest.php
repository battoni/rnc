<?php

namespace App\Modules\Partner\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The trail's page (`GET /api/referrals`, R23): optionally one partner's. */
class ListReferralsRequest extends FormRequest
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
            'partner_id' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::PER_PAGE_MAX],
        ];
    }

    public function partnerId(): ?int
    {
        $partnerId = $this->validated('partner_id');

        return $partnerId === null ? null : (int) $partnerId;
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? self::PER_PAGE_DEFAULT);
    }
}

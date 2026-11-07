<?php

namespace App\Modules\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListUsersRequest extends FormRequest
{
    private const ORDER_BY_OPTIONS = [
        'name_asc',
        'name_desc',
        'status_asc',
        'status_desc',
        'role_asc',
        'role_desc',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['sometimes'],
            'order_by' => ['sometimes', 'nullable', 'string', Rule::in(self::ORDER_BY_OPTIONS)],
            'profile_type' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in(array_keys((array) config('registration.profile_types'))),
            ],
            'per_page' => ['sometimes', 'integer', 'min:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<int>|null
     */
    public function ids(): ?array
    {
        $ids = $this->query('ids');

        if (is_array($ids)) {
            $ids = array_map('intval', $ids);
        }

        if (is_string($ids)) {
            $ids = array_map('intval', array_filter(explode(',', $ids)));
        }

        if (! is_array($ids) || empty($ids)) {
            return null;
        }

        return $ids;
    }

    public function orderBy(): ?string
    {
        $orderBy = $this->validated()['order_by'] ?? null;

        return is_string($orderBy) ? $orderBy : null;
    }

    public function profileType(): ?string
    {
        $profileType = $this->validated()['profile_type'] ?? null;

        return is_string($profileType) && $profileType !== '' ? $profileType : null;
    }
}

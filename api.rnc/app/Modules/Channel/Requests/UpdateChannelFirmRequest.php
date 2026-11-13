<?php

namespace App\Modules\Channel\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A channel firm's edit (`PATCH /api/channel/firms/{id}`, rnc-3 R7, AM9): every field optional, the
 * same bounds as the add. A `stage` sent here is ignored — the advance is its own route.
 */
class UpdateChannelFirmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:'.StoreChannelFirmRequest::NAME_MAX_LENGTH],
            'city' => ['sometimes', 'required', 'string', 'max:'.StoreChannelFirmRequest::CITY_MAX_LENGTH],
            'monthly_fee_cents' => ['sometimes', 'required', ...StoreChannelFirmRequest::feeRule()],
            'share_bp' => ['sometimes', 'required', ...StoreChannelFirmRequest::shareRule()],
            'notes' => ['sometimes', 'nullable', 'string', 'max:'.StoreChannelFirmRequest::NOTES_MAX_LENGTH],
        ];
    }
}

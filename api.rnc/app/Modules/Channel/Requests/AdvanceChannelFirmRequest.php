<?php

namespace App\Modules\Channel\Requests;

use App\Modules\Channel\Enums\ChannelStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One stage forward (`POST /api/channel/firms/{id}/advance`, rnc-3 R7). `to` names the stage the
 * button showed ("Avançar para <próxima etapa>"), so a second click or a stale screen is refused
 * with `channel.invalidTransition` instead of moving the firm twice.
 */
class AdvanceChannelFirmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to' => ['required', 'string', Rule::in(ChannelStage::values())],
        ];
    }

    public function stage(): ChannelStage
    {
        return ChannelStage::from((string) $this->validated('to'));
    }
}

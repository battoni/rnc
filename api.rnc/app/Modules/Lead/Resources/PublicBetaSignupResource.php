<?php

namespace App\Modules\Lead\Resources;

use App\Modules\Lead\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A beta signup as the root site may see it (rnc-4 R29) — an allow-list: the firm's name for the
 * success state and the channel. Never the contact, the token or the id.
 *
 * @mixin Lead
 */
class PublicBetaSignupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'company' => $this->company,
            'channel' => $this->channel,
        ];
    }
}

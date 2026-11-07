<?php

namespace App\Modules\Lead\Resources;

use App\Modules\Lead\Helpers\ConsentTerms;
use App\Modules\Lead\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A lead as the visitor's own browser may see it (plan AM3) — an allow-list. Never the phone, the
 * e-mail, the contact's name, the visitor id or the internal `id`. The token is sent only by the
 * response that creates it (`withToken()`, "A resource is closed by default").
 *
 * @mixin Lead
 */
class PublicLeadResource extends JsonResource
{
    private bool $includesToken = false;

    public function withToken(): self
    {
        $this->includesToken = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $profile = $this->profile;

        return [
            ...($this->includesToken ? ['token' => $this->token] : []),
            'stage' => $this->stage,
            'last_step' => $this->last_step,
            'path' => $this->path,
            'company' => $this->company,
            'cnpj' => $this->cnpj,
            'profile' => [
                'sector' => $profile['sector'] ?? null,
                'regime' => $profile['regime'] ?? null,
                'band' => $profile['band'] ?? null,
                'b2b' => isset($profile['b2b']) ? (int) $profile['b2b'] : null,
                'incentivos' => (bool) ($profile['incentivos'] ?? false),
                'exporta' => (bool) ($profile['exporta'] ?? false),
            ],
            'has_contact' => $this->hasContact(),
            // Whether the booking has to ask for an e-mail (AM5) — never the address itself.
            'has_email' => $this->hasEmail(),
            'consent' => ['text' => ConsentTerms::TEXT, 'version' => ConsentTerms::VERSION],
        ];
    }
}

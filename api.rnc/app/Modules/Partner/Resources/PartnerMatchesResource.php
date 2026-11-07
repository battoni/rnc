<?php

namespace App\Modules\Partner\Resources;

use App\Modules\Partner\Models\Partner;
use App\Modules\Partner\Models\Referral;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The routing panel (R22): whether the lead can be referred and why not, and each active partner
 * with its score, the diagnostic items it covers and the referral already sent to it. A partner's
 * e-mail and phone stay in the registry: the team routes, Reinaldo manages.
 *
 * @property array{can_refer: bool, blocked_reason: string|null, matches: list<array{partner: Partner, score: int, covers: list<array{id: string, title: string}>, referral: Referral|null}>} $resource
 */
class PartnerMatchesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'can_refer' => $this->resource['can_refer'],
            'blocked_reason' => $this->resource['blocked_reason'],
            'matches' => array_map(fn (array $match) => [
                'partner' => [
                    'id' => $match['partner']->id,
                    'name' => $match['partner']->name,
                    'specialty' => $match['partner']->specialty,
                    'city' => $match['partner']->city,
                ],
                'score' => $match['score'],
                'covers' => $match['covers'],
                'referral' => $match['referral'] ? [
                    'id' => $match['referral']->id,
                    'status' => $match['referral']->status,
                    'at' => $match['referral']->created_at->toIso8601String(),
                ] : null,
            ], $this->resource['matches']),
        ];
    }
}

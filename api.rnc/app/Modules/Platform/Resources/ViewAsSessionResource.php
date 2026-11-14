<?php

namespace App\Modules\Platform\Resources;

use App\Modules\Platform\Models\ViewAsSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One view-as session (rnc-4 R26): who entered which firm, from which address, when, and why it
 * ended (`sair` | `logout` | `expirou`; null while open). Operator only.
 *
 * @property ViewAsSession $resource
 */
class ViewAsSessionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $session = $this->resource;
        $operator = $session->operator;
        $firm = $session->firm;

        return [
            'id' => $session->id,
            'operator' => $operator ? ['id' => $operator->id, 'name' => $operator->name] : null,
            'firm' => $firm ? ['id' => $firm->id, 'slug' => $firm->slug, 'name' => $firm->name] : null,
            'ip_address' => $session->ip_address,
            'started_at' => $session->started_at->toIso8601String(),
            'ended_at' => $session->ended_at?->toIso8601String(),
            'end_reason' => $session->end_reason?->value,
        ];
    }
}

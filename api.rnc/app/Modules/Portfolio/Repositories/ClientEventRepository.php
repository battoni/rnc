<?php

namespace App\Modules\Portfolio\Repositories;

use App\Modules\Portfolio\Models\ClientEvent;
use Illuminate\Database\Eloquent\Collection;

/** Clients' timelines (rnc-2 D12). `BelongsToFirm` keeps every read inside the current firm. */
class ClientEventRepository
{
    /** @param  array{firm_id: int, client_id: int, user_id: int|null, type: string, data: array<string, mixed>, at: \DateTimeInterface}  $attributes */
    public function createClientEvent(array $attributes): ClientEvent
    {
        return ClientEvent::query()->create($attributes);
    }

    /** @return Collection<int, ClientEvent> one client's lines, newest first, with who did each */
    public function findClientEventsByClientId(int $clientId): Collection
    {
        return ClientEvent::query()
            ->with('user:id,name,last_name')
            ->where('client_id', $clientId)
            ->orderByDesc('at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Which of the firm's clients hold at least one line of `$type` — one query, whatever the
     * number of clients (plan AM6).
     *
     * @return list<int>
     */
    public function findClientIdsWithClientEvent(string $type): array
    {
        return ClientEvent::query()
            ->where('type', $type)
            ->distinct()
            ->pluck('client_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}

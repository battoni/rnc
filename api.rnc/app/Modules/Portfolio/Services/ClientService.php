<?php

namespace App\Modules\Portfolio\Services;

use App\Modules\Portfolio\Enums\ClientEventType;
use App\Modules\Portfolio\Models\Client;
use App\Modules\Portfolio\Models\ClientEvent;
use App\Modules\Portfolio\Repositories\ClientEventRepository;
use App\Modules\Portfolio\Repositories\ClientRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * A client's timeline (rnc-2 R20, R22, D12). `recordEvent` is the ONLY writer of `client_events`:
 * another module (Fiscal's notice and review) adds a line through it, never with its own insert
 * (arcus "A module reads another module's tables and never writes them").
 */
class ClientService
{
    public function __construct(private ClientRepository $clientRepository, private ClientEventRepository $clientEventRepository) {}

    /**
     * One line, stamped with the client's own firm (never left to `BelongsToFirm`'s `creating`
     * event, which `DatabaseSeeder`'s `WithoutModelEvents` silences) and the moment in UTC.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordEvent(Client $client, ClientEventType $type, array $data, ?int $userId): ClientEvent
    {
        return $this->clientEventRepository->createClientEvent([
            'firm_id' => $client->firm_id,
            'client_id' => $client->id,
            'user_id' => $userId,
            'type' => $type->value,
            'data' => $data,
            // UTC already: a class cast read back on the created model is the value SET, zone kept.
            'at' => CarbonImmutable::now()->utc(),
        ])->load('user:id,name,last_name');
    }

    /** @return Collection<int, ClientEvent>|null the firm client's lines, newest first; null when it is not the firm's */
    public function events(int $clientId): ?Collection
    {
        return $this->clientRepository->findClientById($clientId)
            ? $this->clientEventRepository->findClientEventsByClientId($clientId)
            : null;
    }

    /** @return list<int> the firm's clients holding a line of `$type` */
    public function clientIdsWithEvent(ClientEventType $type): array
    {
        return $this->clientEventRepository->findClientIdsWithClientEvent($type->value);
    }
}

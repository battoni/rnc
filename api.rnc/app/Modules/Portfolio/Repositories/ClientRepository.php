<?php

namespace App\Modules\Portfolio\Repositories;

use App\Models\User;
use App\Modules\Portfolio\Models\Client;
use Illuminate\Database\Eloquent\Collection;

/** The firm's clients. `BelongsToFirm` keeps every query inside the current firm. */
class ClientRepository
{
    /** @return Collection<int, Client> */
    public function findClientsByProfile(?string $sector, ?string $regime): Collection
    {
        return Client::query()
            ->when($sector, fn ($query) => $query->where('sector', $sector))
            ->when($regime, fn ($query) => $query->where('regime', $regime))
            ->orderBy('name')
            ->get();
    }

    public function findClientById(int $id): ?Client
    {
        return Client::query()->find($id);
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Client>
     */
    public function findClientsByIds(array $ids): Collection
    {
        return Client::query()->whereIn('id', $ids)->orderBy('name')->get();
    }

    /**
     * Who marked a repricing, by id (reads Auth's `users`).
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function findUserNamesByIds(array $ids): array
    {
        return User::query()->whereIn('id', $ids)->pluck('name', 'id')->all();
    }

    public function countMarkedClients(): int
    {
        return Client::query()->whereNotNull('repricing')->count();
    }

    /** @param  array{at: string, by: int, pct: int, suggested: float}  $repricing */
    public function markClientRepricing(Client $client, array $repricing): Client
    {
        $client->forceFill(['repricing' => $repricing])->save();

        return $client;
    }

    /**
     * One row per CNPJ per firm: an existing CNPJ is updated, a new one created.
     *
     * @param  array{name: string, cnpj: string, sector: string, regime: string, band: string, b2b_share: int, monthly_fee: float}  $attributes
     */
    public function upsertClientByCnpj(array $attributes): Client
    {
        return Client::query()->updateOrCreate(['cnpj' => $attributes['cnpj']], $attributes);
    }
}

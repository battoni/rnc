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

    /**
     * The firm's clients of some regimes, by id — Fiscal's readiness and supplier-credit lists
     * (rnc-2 R18, R21: Presumido and Real), read only. One query whatever the portfolio's size.
     *
     * @param  list<string>  $regimes
     * @return Collection<int, Client>
     */
    public function findClientsByRegimes(array $regimes, ?string $sector = null): Collection
    {
        return Client::query()
            ->whereIn('regime', $regimes)
            ->when($sector, fn ($query) => $query->where('sector', $sector))
            ->orderBy('id')
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
     * The firm's client ids by CNPJ (14 digits), for Fiscal's NF-e matching (rnc-2 R15, D6) — read
     * only; a CNPJ that is not a client is absent from the answer.
     *
     * @param  list<string>  $cnpjs
     * @return array<string, int>
     */
    public function findClientIdsByCnpjs(array $cnpjs): array
    {
        return Client::query()->whereIn('cnpj', $cnpjs)->pluck('id', 'cnpj')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * The firm's clients by CNPJ, keyed by it — what a re-import is about to update (rnc-2 R28: an
     * unchanged consent keeps its moment). One query.
     *
     * @param  list<string>  $cnpjs
     * @return Collection<string, Client>
     */
    public function findClientsByCnpjs(array $cnpjs): Collection
    {
        return Client::query()->whereIn('cnpj', $cnpjs)->get()->keyBy('cnpj');
    }

    /**
     * The whole portfolio, by id — the Radar runs the rules engine over every client (rnc-2 R24).
     * One query whatever its size.
     *
     * @return Collection<int, Client>
     */
    public function findAllClients(): Collection
    {
        return Client::query()->orderBy('id')->get();
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
     * @param  array<string, mixed>  $attributes  the CSV's columns, and the consent when the file has it
     */
    public function upsertClientByCnpj(array $attributes): Client
    {
        return Client::query()->updateOrCreate(['cnpj' => $attributes['cnpj']], $attributes);
    }
}

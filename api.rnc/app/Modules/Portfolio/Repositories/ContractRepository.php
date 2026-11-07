<?php

namespace App\Modules\Portfolio\Repositories;

use App\Modules\Portfolio\Helpers\ContractExposure;
use App\Modules\Portfolio\Models\ClientContract;
use Illuminate\Database\Eloquent\Collection;

/** The firm's client contracts (rnc-3 R17). `BelongsToFirm` keeps every query inside the current firm. */
class ContractRepository
{
    /**
     * The contracts the alert lists (R19): ending in the transition or later, without the clause,
     * with their client's id and name — two queries whatever their number (P13).
     *
     * @return Collection<int, ClientContract>
     */
    public function findContractsAtRisk(): Collection
    {
        return ClientContract::query()
            ->where('has_reforma_clause', false)
            ->where('end_year', '>=', ContractExposure::FIRST_YEAR)
            ->with('client:id,name')
            ->orderBy('id')
            ->get();
    }

    public function countContracts(): int
    {
        return ClientContract::query()->count();
    }

    public function findContractById(int $id): ?ClientContract
    {
        return ClientContract::query()->find($id);
    }

    /**
     * The firm's contracts of these clients, locked for the import's updates — one query for the
     * whole file, whatever its number of lines.
     *
     * @param  list<int>  $clientIds
     * @return Collection<int, ClientContract>
     */
    public function findContractsByClientIds(array $clientIds): Collection
    {
        return ClientContract::query()->whereIn('client_id', $clientIds)->lockForUpdate()->get();
    }

    /** @param  array<string, mixed>  $attributes */
    public function createContract(array $attributes): ClientContract
    {
        return ClientContract::query()->create($attributes);
    }

    /** @param  array<string, mixed>  $attributes */
    public function updateContract(ClientContract $contract, array $attributes): ClientContract
    {
        $contract->forceFill($attributes)->save();

        return $contract;
    }

    /** The contract, re-read and locked, for "Cláusula incluída" (R22). */
    public function lockContract(int $id): ?ClientContract
    {
        return ClientContract::query()->lockForUpdate()->find($id);
    }
}

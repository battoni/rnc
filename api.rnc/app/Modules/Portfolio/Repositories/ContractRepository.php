<?php

namespace App\Modules\Portfolio\Repositories;

use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Portfolio\Helpers\ContractExposure;
use App\Modules\Portfolio\Models\ClientContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/** The firm's client contracts (rnc-3 R17). `BelongsToFirm` keeps every query inside the current firm. */
class ContractRepository
{
    public function __construct(private CurrentFirm $currentFirm) {}

    /**
     * The contracts the alert lists (R19): ending in the transition or later, without the clause,
     * with their client's id, name and partner consent (R20) — two queries whatever their number (P13).
     *
     * @return Collection<int, ClientContract>
     */
    public function findContractsAtRisk(): Collection
    {
        return ClientContract::query()
            ->where('has_reforma_clause', false)
            ->where('end_year', '>=', ContractExposure::FIRST_YEAR)
            ->with('client:id,name,partner_consent,partner_consent_via')
            ->orderBy('id')
            ->get();
    }

    /**
     * The partners each of these contracts was sent to (rnc-3 R20, "Com <parceiro>"), in send order,
     * as contract id → [{id, name}]. A declared read of Partner's `referrals` and `partners` (Portfolio
     * RULES): Partner writes them, through its own service. One query whatever the number.
     *
     * @param  list<int>  $contractIds
     * @return array<int, list<array{id: int, name: string}>>
     */
    public function findReferredPartnersByContract(array $contractIds): array
    {
        if ($contractIds === []) {
            return [];
        }

        $rows = DB::table('referrals')
            ->join('partners', 'partners.id', '=', 'referrals.partner_id')
            ->where('referrals.firm_id', $this->currentFirm->id())
            ->whereIn('referrals.contract_id', $contractIds)
            ->orderBy('referrals.id')
            ->get(['referrals.contract_id', 'partners.id', 'partners.name']);

        $partners = [];

        foreach ($rows as $row) {
            $partners[(int) $row->contract_id][] = ['id' => (int) $row->id, 'name' => (string) $row->name];
        }

        return $partners;
    }

    public function countContracts(): int
    {
        return ClientContract::query()->count();
    }

    public function findContractById(int $id): ?ClientContract
    {
        return ClientContract::query()->find($id);
    }

    /** The contract with its client, for a legal referral (rnc-3 R20) — read by Partner. */
    public function findContractWithClientById(int $id): ?ClientContract
    {
        return ClientContract::query()->with('client')->find($id);
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

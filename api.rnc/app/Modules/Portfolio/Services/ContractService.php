<?php

namespace App\Modules\Portfolio\Services;

use App\Models\User;
use App\Modules\Portfolio\Enums\ClientEventType;
use App\Modules\Portfolio\Helpers\ContractExposure;
use App\Modules\Portfolio\Models\ClientContract;
use App\Modules\Portfolio\Repositories\ContractRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The contract alert (rnc-3 R19, R22).
 *
 * `alert()` — the contracts ending in 2027 or later without the clause, by exposure (annual value ×
 * years in 2027–2033, `ContractExposure`) highest first, ties by id, and the KPIs: at risk of all,
 * the at-risk ones' annual value and exposure. Three queries whatever the portfolio (P13); the
 * ranking is PHP over the at-risk rows, the same arithmetic as the rows.
 *
 * `markClause()` — "Cláusula incluída": sets the clause with who and when and writes one
 * `contrato.clausula` line on the client's timeline through `ClientService::recordEvent`, in one
 * transaction on the locked row. A contract already marked is answered as it is (W79).
 */
class ContractService
{
    public function __construct(
        private ContractRepository $contractRepository,
        private ClientService $clientService,
    ) {}

    /** @return array{summary: array{at_risk: int, total: int, annual_value_cents: int, exposure_cents: int}, contracts: Collection<int, ClientContract>} */
    public function alert(): array
    {
        $contracts = $this->contractRepository->findContractsAtRisk()
            ->sortBy([
                fn (ClientContract $a, ClientContract $b) => $this->exposureOf($b) <=> $this->exposureOf($a),
                fn (ClientContract $a, ClientContract $b) => $a->id <=> $b->id,
            ])
            ->values();

        return [
            'summary' => [
                'at_risk' => $contracts->count(),
                'total' => $this->contractRepository->countContracts(),
                'annual_value_cents' => (int) $contracts->sum('annual_value_cents'),
                'exposure_cents' => (int) $contracts->sum(fn (ClientContract $contract) => $this->exposureOf($contract)),
            ],
            'contracts' => $contracts->toBase(),
        ];
    }

    /** @return ClientContract|null null when the contract is not the firm's */
    public function markClause(int $id, User $user): ?ClientContract
    {
        return DB::transaction(function () use ($id, $user) {
            $contract = $this->contractRepository->lockContract($id);

            if (! $contract || $contract->isClauseMarked()) {
                return $contract;
            }

            $this->contractRepository->updateContract($contract, [
                'has_reforma_clause' => true,
                // UTC already: the class cast read back is the value SET, zone kept (Portfolio RULES).
                'clause_marked_at' => CarbonImmutable::now()->utc(),
                'clause_marked_by' => $user->id,
            ]);

            $this->clientService->recordEvent($contract->client, ClientEventType::ContractClause, [
                'contract_id' => $contract->id,
                'kind' => $contract->kind,
                'end_year' => $contract->end_year,
            ], $user->id);

            return $contract;
        });
    }

    private function exposureOf(ClientContract $contract): int
    {
        return ContractExposure::cents($contract->annual_value_cents, $contract->start_year, $contract->end_year);
    }
}

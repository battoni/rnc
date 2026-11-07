<?php

namespace Database\Seeders;

use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Portfolio\Enums\ContractKind;
use App\Modules\Portfolio\Models\Client;
use App\Modules\Portfolio\Models\ClientContract;
use Illuminate\Database\Seeder;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * The contract alert's contracts (rnc-3 R17, R29, D10), outside production only: the demo's 40
 * (`demos/franco-reforma-hub/src/seed.js` → `s.contracts`) — its five kinds, R$ 40.000 to 1.200.000 a
 * year in steps of R$ 10.000, starting 2022–2026, ending 2026–2033, about 30 % with the Reforma
 * clause — so some end in 2026 or carry the clause and the alert has something to leave out.
 *
 * Every contract sits on one of `ClientSeeder`'s invented clients (CNPJ root `999`, branch `9999` —
 * AM10), never on a client imported by hand; deterministic (Mt19937 seeded 2033), so the alert reads
 * the same on every fresh seed. Runs after `ClientSeeder`, inside `CurrentFirm::runAs`, and writes
 * `firm_id` itself (`DatabaseSeeder` runs without model events). Re-running rewrites the 40 rows to
 * exactly this draw; no clause is marked.
 *
 * rnc-3 F6b (R20): this draw puts 5 of the 27 at-risk contracts on clients whose service contract
 * gives no partner consent (`ClientSeeder`'s ~15 %), so the blocked "Encaminhar ao jurídico" shows on
 * a fresh seed — `ContractAlertTest` holds it at ≥ 5; a change to either seeder that drops it fails there.
 */
class ContractSeeder extends Seeder
{
    public const COUNT = 40;

    private const RANDOM_SEED = 2033;

    /** `ClientSeeder`'s invented CNPJs: `999` + five digits + branch `9999` + two check digits. */
    private const SEEDED_CLIENT_CNPJ = '999_____9999__';

    private const VALUE_STEPS = [4, 120];

    private const VALUE_STEP_CENTS = 1_000_000;

    private const START_YEARS_BACK = [0, 4];

    private const LAST_START_YEAR = 2026;

    private const END_YEARS = [2026, 2033];

    private const CLAUSE_PERCENT = 30;

    public function run(CurrentFirm $currentFirm): void
    {
        if (app()->environment('production')) {
            return;
        }

        $firm = Firm::where('slug', FirmSeeder::FRANCO_SLUG)->first();

        if (! $firm) {
            return;
        }

        $currentFirm->runAs($firm, function () use ($firm) {
            $clientIds = Client::query()->where('cnpj', 'like', self::SEEDED_CLIENT_CNPJ)->orderBy('id')->pluck('id')->all();

            if ($clientIds === []) {
                return;
            }

            foreach ($this->contractsOf($clientIds) as $contract) {
                $row = ClientContract::query()->firstOrNew([
                    'client_id' => $contract['client_id'],
                    'kind' => $contract['kind'],
                    'start_year' => $contract['start_year'],
                    'end_year' => $contract['end_year'],
                ]);
                $row->forceFill([...$contract, 'firm_id' => $firm->id, 'clause_marked_at' => null, 'clause_marked_by' => null])->save();
            }
        });
    }

    /**
     * @param  list<int>  $clientIds
     * @return list<array{client_id: int, kind: string, annual_value_cents: int, start_year: int, end_year: int, has_reforma_clause: bool}>
     */
    private function contractsOf(array $clientIds): array
    {
        $random = new Randomizer(new Mt19937(self::RANDOM_SEED));
        $kinds = array_column(ContractKind::cases(), 'value');
        $contracts = [];

        while (count($contracts) < self::COUNT) {
            $contract = [
                'client_id' => $clientIds[$random->getInt(0, count($clientIds) - 1)],
                'kind' => $kinds[$random->getInt(0, count($kinds) - 1)],
                'annual_value_cents' => $random->getInt(...self::VALUE_STEPS) * self::VALUE_STEP_CENTS,
                'start_year' => self::LAST_START_YEAR - $random->getInt(...self::START_YEARS_BACK),
                'end_year' => $random->getInt(...self::END_YEARS),
                'has_reforma_clause' => $random->getInt(1, 100) <= self::CLAUSE_PERCENT,
            ];

            // One row per client, kind, start and end: a repeated key is drawn again.
            $contracts[implode(':', [$contract['client_id'], $contract['kind'], $contract['start_year'], $contract['end_year']])] ??= $contract;
        }

        return array_values($contracts);
    }
}

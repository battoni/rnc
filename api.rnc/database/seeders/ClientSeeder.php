<?php

namespace Database\Seeders;

use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Portfolio\Models\Client;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Franco's portfolio to reprice (rnc-1 F8, R25), outside production only: 500 invented companies
 * across the six sectors, the three regimes and the five bands, with a monthly fee by band and regime
 * (the demo's W27: R$ 900–9.000, ±10 %, rounded to R$ 50). Deterministic (a seeded Mersenne
 * Twister), so the repricing screen shows the same numbers on every `make fresh-seed`.
 *
 * Every CNPJ passes the check digits but is plainly invented: the root is `999` + the client's
 * number (`999.00NNN`) and the branch is `9999` — a pattern a reader spots at once and that is never
 * looked up (BrasilAPI is not called for clients). Names are invented words, never a real company.
 *
 * rnc-2 F9 (R28): every client answered the contract's partner clause — about 85 % yes, the rest
 * no (the demo's `CLIENT_CONSENT_YES`), `via = contrato`, on 15 January of a year between 2019 and
 * 2025. Drawn from a SECOND seeded generator, so the profiles and fees above stay what they were.
 */
class ClientSeeder extends Seeder
{
    public const COUNT = 500;

    private const RANDOM_SEED = 2027;

    private const CONSENT_RANDOM_SEED = 2028;

    /** Percent of the clients that said yes to the contract's partner clause (plan Defaults). */
    private const CONSENT_YES_PERCENT = 85;

    private const CONSENT_YEARS = [2019, 2025];

    private const CNPJ_ROOT_PREFIX = '999';

    private const CNPJ_BRANCH = '9999';

    private const CNPJ_WEIGHTS = [[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]];

    private const FEE_BY_BAND = ['b1' => 900, 'b2' => 1800, 'b3' => 3500, 'b4' => 6000, 'b5' => 8000];

    private const FEE_BY_REGIME = ['simples' => 1.0, 'presumido' => 1.1, 'real' => 1.2];

    private const FEE_RANGE = [900, 9000];

    private const FEE_ROUNDING = 50;

    /** Percent weight of each band in the portfolio: small companies are most of it. */
    private const BAND_WEIGHTS = ['b1' => 25, 'b2' => 35, 'b3' => 22, 'b4' => 12, 'b5' => 6];

    /** The regime by band: Simples stops at R$ 4,8 milhões, the largest are Lucro Real. */
    private const REGIME_WEIGHTS = [
        'b1' => ['simples' => 90, 'presumido' => 10],
        'b2' => ['simples' => 65, 'presumido' => 30, 'real' => 5],
        'b3' => ['presumido' => 60, 'real' => 40],
        'b4' => ['presumido' => 30, 'real' => 70],
        'b5' => ['real' => 100],
    ];

    /** The share sold to companies, by sector, in steps of 5 %. */
    private const B2B_RANGE = [
        'comercio' => [5, 40], 'industria' => [60, 100], 'servicos' => [30, 90],
        'saude' => [5, 40], 'educacao' => [0, 20], 'agro' => [70, 100],
    ];

    private const B2B_STEP = 5;

    private const NAME_PREFIXES = [
        'comercio' => ['Empório', 'Mercado', 'Distribuidora', 'Armazém', 'Casa'],
        'industria' => ['Metalúrgica', 'Indústria', 'Plásticos', 'Têxtil', 'Moldados'],
        'servicos' => ['Consultoria', 'Agência', 'Soluções', 'Engenharia', 'Logística'],
        'saude' => ['Clínica', 'Laboratório', 'Odontologia', 'Fisioterapia', 'Centro Médico'],
        'educacao' => ['Colégio', 'Escola', 'Instituto', 'Cursos', 'Centro Educacional'],
        'agro' => ['Agropecuária', 'Fazenda', 'Grãos', 'Cooperativa', 'Agrícola'],
    ];

    private const NAME_WORDS = [
        'Aurora', 'Bandeira', 'Cedral', 'Delta Azul', 'Esteio', 'Farol Alto', 'Girassol', 'Horizonte Novo',
        'Ipê Roxo', 'Jatobá', 'Lumiar', 'Marajoara', 'Nascente', 'Orla Sul', 'Pinhal', 'Quaresmeira',
        'Riacho Doce', 'Serra Clara', 'Tupinambá', 'Urupema', 'Vereda', 'Xaréu', 'Ypê Branco', 'Zênite',
        'Acauã', 'Buriti', 'Caraíba', 'Dourado', 'Embaúba', 'Figueira', 'Guará', 'Itaúna Verde',
    ];

    private const NAME_SUFFIXES = ['Ltda', 'Ltda', 'Ltda', 'EIRELI', 'S.A.'];

    public function run(CurrentFirm $currentFirm): void
    {
        if (app()->environment('production')) {
            return;
        }

        $firm = Firm::where('slug', FirmSeeder::FRANCO_SLUG)->first();

        if (! $firm) {
            return;
        }

        $currentFirm->runAs($firm, fn () => Client::query()->insert($this->clientsOf($firm)));
    }

    /** @return list<array<string, mixed>> */
    private function clientsOf(Firm $firm): array
    {
        $random = new Randomizer(new Mt19937(self::RANDOM_SEED));
        $consentRandom = new Randomizer(new Mt19937(self::CONSENT_RANDOM_SEED));
        $sectors = array_keys(self::B2B_RANGE);
        $now = CarbonImmutable::now();
        $names = [];
        $rows = [];

        for ($number = 1; $number <= self::COUNT; $number++) {
            $sector = $sectors[($number - 1) % count($sectors)];
            $band = $this->weighted($random, self::BAND_WEIGHTS);
            $regime = $this->weighted($random, self::REGIME_WEIGHTS[$band]);
            [$lowShare, $highShare] = self::B2B_RANGE[$sector];
            $fee = self::FEE_BY_BAND[$band] * self::FEE_BY_REGIME[$regime] * (0.9 + $random->getInt(0, 200) / 1000);

            $rows[] = [
                'firm_id' => $firm->id,
                'name' => $this->uniqueName($random, $sector, $names),
                'cnpj' => $this->inventedCnpj($number),
                'sector' => $sector,
                'regime' => $regime,
                'band' => $band,
                'b2b_share' => $random->getInt(intdiv($lowShare, self::B2B_STEP), intdiv($highShare, self::B2B_STEP)) * self::B2B_STEP,
                'monthly_fee' => min(self::FEE_RANGE[1], max(self::FEE_RANGE[0], round($fee / self::FEE_ROUNDING) * self::FEE_ROUNDING)),
                'repricing' => null,
                'partner_consent' => $consentRandom->getInt(1, 100) <= self::CONSENT_YES_PERCENT,
                'partner_consent_at' => CarbonImmutable::create($consentRandom->getInt(...self::CONSENT_YEARS), 1, 15, 12, 0, 0, 'UTC')->format('Y-m-d H:i:s'),
                'partner_consent_via' => Client::CONSENT_VIA_CONTRACT,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }

    /** @param  array<string, int>  $weights */
    private function weighted(Randomizer $random, array $weights): string
    {
        $roll = $random->getInt(1, array_sum($weights));

        foreach ($weights as $key => $weight) {
            $roll -= $weight;

            if ($roll <= 0) {
                return $key;
            }
        }

        return array_key_last($weights);
    }

    /** @param  array<string, true>  $names */
    private function uniqueName(Randomizer $random, string $sector, array &$names): string
    {
        do {
            $prefix = self::NAME_PREFIXES[$sector][$random->getInt(0, count(self::NAME_PREFIXES[$sector]) - 1)];
            $word = self::NAME_WORDS[$random->getInt(0, count(self::NAME_WORDS) - 1)];
            $suffix = self::NAME_SUFFIXES[$random->getInt(0, count(self::NAME_SUFFIXES) - 1)];
            $name = "{$prefix} {$word} {$suffix}";
        } while (isset($names[$name]));

        $names[$name] = true;

        return $name;
    }

    /** `999` + the client's number on five digits, branch `9999`, and the two check digits. */
    private function inventedCnpj(int $number): string
    {
        $base = self::CNPJ_ROOT_PREFIX.str_pad((string) $number, 5, '0', STR_PAD_LEFT).self::CNPJ_BRANCH;

        foreach (self::CNPJ_WEIGHTS as $weights) {
            $sum = 0;

            foreach ($weights as $position => $weight) {
                $sum += (int) $base[$position] * $weight;
            }

            $rest = $sum % 11;
            $base .= $rest < 2 ? '0' : (string) (11 - $rest);
        }

        return $base;
    }
}

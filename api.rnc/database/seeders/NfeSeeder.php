<?php

namespace Database\Seeders;

use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Fiscal\Enums\NfeImportSource;
use App\Modules\Fiscal\Repositories\NfeDocumentRepository;
use App\Modules\Fiscal\Services\NfeImporter;
use App\Modules\Portfolio\Models\Client;
use Carbon\CarbonImmutable;
use Database\Seeders\Support\GeneratedNfeSource;
use Database\Seeders\Support\NfeXmlBuilder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Franco's clients' NF-e (rnc-2 F6b, R15–R17, R29; plan AM7), outside production and outside the
 * Pest suite: 90 days of invented XMLs for the first 40 Presumido/Real clients of `ClientSeeder` —
 * 15–60 sales and 10–40 purchases each — built by `NfeXmlBuilder` and imported through
 * `NfeImporter`, the upload's own code path, one batch (`folder`, no user) per client.
 * Deterministic (a seeded Mersenne Twister); the dates count back from today.
 *
 * Readiness follows the demo's tiers (`seed.js`): ≈ 46 % of clients fill 95–100 % of their sales,
 * 50 % 80–94 %, 4 % 45–79 %; an incomplete sale misses the IBS/CBS group (the 1115 risk) more often
 * the later its client is, else its codes or the 2026 test rates. A sale goes to a company (CNPJ) by
 * the client's B2B share, else to a consumer (CPF). Purchases come from suppliers whose CRT is
 * Simples (1) by the demo's `SIMPLES_SHARE_BY_BAND` (12 % of clients buy from none), else regime
 * normal (3). Every number is plainly invented: buyers `997…`, suppliers `998…`, CPFs `999…`.
 *
 * Unlike the other seeders it keeps model events on (no `WithoutModelEvents`): the rows are the
 * importer's, stamped by `BelongsToFirm` inside `CurrentFirm::runAs`, as an upload's are.
 *
 * First it deletes `storage/app/private/nfe/<firm id>/` — after `migrate:fresh` the rows are gone, and
 * the files of the previous seed would be orphans (AM7).
 */
class NfeSeeder extends Seeder
{
    public const CLIENT_COUNT = 40;

    public const DAYS = 90;

    private const RANDOM_SEED = 2027;

    private const ZONE = 'America/Sao_Paulo';

    private const SALES_RANGE = [15, 60];

    private const PURCHASES_RANGE = [10, 40];

    private const ITEMS_RANGE = [1, 4];

    /** [share of clients, lowest and highest % of complete sales] — the demo's 46 / 50 / 4 %. */
    private const TIERS = [[46, 95, 100], [50, 80, 94], [4, 45, 79]];

    /** Of an incomplete sale, the % that miss the whole group (the 1115 risk), by tier. */
    private const MISSING_GROUP_BY_TIER = [5, 15, 40];

    /** Of the rest, the % with blank codes (the others miss the 2026 test rates) — the demo's 60 %. */
    private const MISSING_CODES_SHARE = 60;

    /** The demo's `SIMPLES_SHARE_BY_BAND`: % of purchases bought from Simples suppliers. */
    private const SIMPLES_SHARE_BY_BAND = ['b1' => [20, 60], 'b2' => [10, 50], 'b3' => [5, 25], 'b4' => [2, 12], 'b5' => [1, 5]];

    private const NO_SIMPLES_SUPPLIERS = 12;

    /** The demo's `B2B_SALES_BY_BAND`, in reais a month: what a client's sales add up to. */
    private const MONTHLY_SALES_BY_BAND = ['b1' => 15_000, 'b2' => 100_000, 'b3' => 500_000, 'b4' => 1_500_000, 'b5' => 4_000_000];

    private const PURCHASES_SHARE = 0.5;

    private const SYSTEMS = ['TOTVS Protheus 12.1', 'SAP Business One 10.0', 'Omie 2.4', 'Sankhya 4.2', 'Bling 3.1', 'ERP próprio 1.0'];

    private const BUYERS = 200;

    private const SUPPLIERS = 120;

    private const BUYER_ROOT = '997';

    private const SUPPLIER_ROOT = '998';

    private const CRT_SIMPLES = 1;

    private const CRT_NORMAL = 3;

    private int $supplierNumber = 0;

    private int $cpfNumber = 0;

    public function run(CurrentFirm $currentFirm, NfeImporter $nfeImporter): void
    {
        if (app()->environment(['production', 'testing'])) {
            return;
        }

        $firm = Firm::where('slug', FirmSeeder::FRANCO_SLUG)->first();

        if (! $firm) {
            return;
        }

        $started = microtime(true);
        Storage::disk(NfeDocumentRepository::DISK)->deleteDirectory('nfe/'.$firm->id);

        [$documents, $stored, $refused] = $currentFirm->runAs($firm, fn () => $this->importAll($nfeImporter));

        $seconds = number_format(microtime(true) - $started, 1, ',', '');
        $this->command->info("NfeSeeder: {$documents} XMLs de ".self::CLIENT_COUNT." clientes → {$stored} documentos guardados, {$refused} recusas, em {$seconds} s.");
    }

    /** @return array{0: int, 1: int, 2: int} files read, documents stored, refusals */
    private function importAll(NfeImporter $nfeImporter): array
    {
        $random = new Randomizer(new Mt19937(self::RANDOM_SEED));
        $today = CarbonImmutable::now(self::ZONE)->startOfDay();
        $clients = Client::query()->whereIn('regime', ['presumido', 'real'])->orderBy('id')->limit(self::CLIENT_COUNT)->get();
        $totals = [0, 0, 0];

        foreach ($clients as $client) {
            $import = $nfeImporter->import(new GeneratedNfeSource($this->documentsOf($client, $random, $today)), NfeImportSource::Folder, null);
            $totals = [$totals[0] + $import->files, $totals[1] + $import->stored, $totals[2] + count($import->refused)];
        }

        return $totals;
    }

    /** @return iterable<string, string> file name → XML, the client's sales then its purchases */
    private function documentsOf(Client $client, Randomizer $random, CarbonImmutable $today): iterable
    {
        $tier = $this->tierOf($random);
        [, $lowFilled, $highFilled] = self::TIERS[$tier];
        $filled = $random->getInt($lowFilled, $highFilled);
        $system = self::SYSTEMS[$random->getInt(0, count(self::SYSTEMS) - 1)];
        $sales = $random->getInt(...self::SALES_RANGE);
        $purchases = $random->getInt(...self::PURCHASES_RANGE);
        $saleCents = intdiv(self::MONTHLY_SALES_BY_BAND[$client->band] * 100 * 3, $sales);
        $purchaseCents = (int) (self::MONTHLY_SALES_BY_BAND[$client->band] * 100 * 3 * self::PURCHASES_SHARE / $purchases);

        for ($number = 1; $number <= $sales; $number++) {
            $isToCompany = $random->getInt(1, 100) <= $client->b2b_share;

            yield "venda-{$client->id}-{$number}.xml" => NfeXmlBuilder::nfe([
                'emitter' => $client->cnpj,
                'crt' => self::CRT_NORMAL,
                'number' => $number,
                'issued' => $this->issuedAt($random, $today),
                'system' => $system,
                'total' => $this->reais($this->spread($random, $saleCents)),
                'items' => $this->saleItems($random, $filled, $tier),
                ...($isToCompany
                    ? ['recipient' => NfeXmlBuilder::inventedCnpj(self::BUYER_ROOT, $random->getInt(1, self::BUYERS))]
                    : ['recipient_cpf' => NfeXmlBuilder::inventedCpf(++$this->cpfNumber)]),
            ]);
        }

        $simplesShare = $this->simplesShareOf($random, $client->band);

        for ($index = 1; $index <= $purchases; $index++) {
            yield "compra-{$client->id}-{$index}.xml" => NfeXmlBuilder::nfe([
                'emitter' => NfeXmlBuilder::inventedCnpj(self::SUPPLIER_ROOT, $random->getInt(1, self::SUPPLIERS)),
                'crt' => $random->getInt(1, 100) <= $simplesShare ? self::CRT_SIMPLES : self::CRT_NORMAL,
                'recipient' => $client->cnpj,
                'number' => ++$this->supplierNumber,
                'issued' => $this->issuedAt($random, $today),
                'system' => self::SYSTEMS[$random->getInt(0, count(self::SYSTEMS) - 1)],
                'total' => $this->reais($this->spread($random, $purchaseCents)),
                'items' => array_fill(0, $random->getInt(...self::ITEMS_RANGE), NfeXmlBuilder::COMPLETE_ITEM),
            ]);
        }
    }

    private function tierOf(Randomizer $random): int
    {
        $roll = $random->getInt(1, 100);

        foreach (self::TIERS as $tier => [$share]) {
            $roll -= $share;

            if ($roll <= 0) {
                return $tier;
            }
        }

        return array_key_last(self::TIERS);
    }

    /**
     * A sale's items: complete when the roll falls within the client's `$filled` %, else one item
     * fails — the group missing (by tier), the codes blank, or the 2026 test rates not shown.
     *
     * @return list<array<string, mixed>>
     */
    private function saleItems(Randomizer $random, int $filled, int $tier): array
    {
        $items = array_fill(0, $random->getInt(...self::ITEMS_RANGE), NfeXmlBuilder::COMPLETE_ITEM);

        if ($random->getInt(1, 100) <= $filled) {
            return $items;
        }

        $failing = $random->getInt(0, count($items) - 1);
        $isMissingGroup = $random->getInt(1, 100) <= self::MISSING_GROUP_BY_TIER[$tier];
        $isMissingCodes = $random->getInt(1, 100) <= self::MISSING_CODES_SHARE;

        $items[$failing] = match (true) {
            $isMissingGroup => ['group' => false],
            $isMissingCodes => ['class' => ''],
            default => ['cbs' => '0.0000', 'ibs_state' => '0.0000'],
        };

        return $items;
    }

    private function simplesShareOf(Randomizer $random, string $band): int
    {
        if ($random->getInt(1, 100) <= self::NO_SIMPLES_SUPPLIERS) {
            return 0;
        }

        return $random->getInt(...self::SIMPLES_SHARE_BY_BAND[$band]);
    }

    /** A business-hours moment 1–90 days back (never the future), São Paulo time, ISO-8601 with offset. */
    private function issuedAt(Randomizer $random, CarbonImmutable $today): string
    {
        return $today->subDays($random->getInt(1, self::DAYS))
            ->setTime($random->getInt(8, 18), $random->getInt(0, 59), $random->getInt(0, 59))
            ->format('Y-m-d\TH:i:sP');
    }

    /** ±50 % around `$cents`, never under R$ 10. */
    private function spread(Randomizer $random, int $cents): int
    {
        return max(1_000, intdiv($cents * $random->getInt(50, 150), 100));
    }

    private function reais(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}

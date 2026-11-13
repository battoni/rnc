<?php

namespace Database\Seeders;

use App\Modules\Channel\Models\ChannelFirm;
use App\Modules\Channel\Services\ChannelService;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\CurrentFirm;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Franco's RNC channel (rnc-3 R6, R29; D5), outside production only: the demo's six invented member
 * firms (`demos/franco-reforma-hub/src/seed.js` `RNC_FIRMS`), each "(exemplo)" — three ativo — on the
 * `config/channel.php` fee and share. `stage_at` sits 5 to 90 days back, fixed per firm so the board
 * reads the same on every fresh seed. No stage events: the seed starts each firm where the demo has it.
 *
 * Runs inside `CurrentFirm::runAs` and writes `firm_id` itself (`DatabaseSeeder` runs without model
 * events). Re-running rewrites the six rows to exactly this table.
 */
class ChannelSeeder extends Seeder
{
    /** @var list<array{0: string, 1: string, 2: string, 3: int}> name, city, stage, days in the stage */
    private const FIRMS = [
        ['Contábil Serra Azul (exemplo)', 'Belo Horizonte-MG', 'ativo', 90],
        ['Prisma Contabilidade (exemplo)', 'Curitiba-PR', 'ativo', 62],
        ['Litoral Assessoria Contábil (exemplo)', 'Santos-SP', 'implantacao', 18],
        ['Cerrado Contadores (exemplo)', 'Goiânia-GO', 'demo', 9],
        ['Pampa Gestão Contábil (exemplo)', 'Porto Alegre-RS', 'ativo', 35],
        ['Mandacaru Contabilidade (exemplo)', 'Recife-PE', 'contato', 5],
    ];

    public function run(CurrentFirm $currentFirm): void
    {
        if (app()->environment('production')) {
            return;
        }

        $firm = Firm::where('slug', FirmSeeder::FRANCO_SLUG)->first();

        if (! $firm) {
            return;
        }

        $now = CarbonImmutable::now();
        $defaults = ChannelService::defaults();

        $currentFirm->runAs($firm, function () use ($firm, $now, $defaults) {
            foreach (self::FIRMS as [$name, $city, $stage, $days]) {
                $row = ChannelFirm::query()->firstOrNew(['name' => $name]);
                $row->forceFill([
                    ...$defaults,
                    'firm_id' => $firm->id,
                    'name' => $name,
                    'city' => $city,
                    'stage' => $stage,
                    'stage_at' => $now->subDays($days),
                    'notes' => null,
                ])->save();
            }
        });
    }
}

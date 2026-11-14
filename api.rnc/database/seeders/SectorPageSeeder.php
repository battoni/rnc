<?php

namespace Database\Seeders;

use App\Modules\Content\Services\SectorPageService;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\CurrentFirm;
use Illuminate\Database\Seeder;

/**
 * Franco's six sector pages (rnc-1 R6): the default set (`DefaultSectorPages`, rnc-4 F3b1 — the same
 * set `firm:create` gives a new firm). Reference content, so it runs on every environment; re-running
 * it rewrites the text in place and never duplicates a page.
 */
class SectorPageSeeder extends Seeder
{
    public function run(CurrentFirm $currentFirm, SectorPageService $sectorPageService): void
    {
        $franco = Firm::where('slug', FirmSeeder::FRANCO_SLUG)->first();

        if (! $franco) {
            return;
        }

        // As Franco, so the lookup by slug sees Franco's rows (`FirmScope`).
        $currentFirm->runAs($franco, fn () => $sectorPageService->startFirm($franco));
    }
}

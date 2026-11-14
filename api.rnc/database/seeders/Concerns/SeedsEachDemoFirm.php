<?php

namespace Database\Seeders\Concerns;

use App\Models\User;
use App\Modules\Firm\Helpers\FirmModules;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\CurrentFirm;
use Database\Seeders\DemoFirmSeeder;
use Database\Seeders\FirmSeeder;
use Illuminate\Support\Collection;

/**
 * Nothing runs outside `local` and `testing` (`DemoEnvironment`, verify P2-C2).
 *
 * The demo seeders run once per seeded firm (rnc-4 F3b2, D18): Franco, then the demo firm Alvorada
 * when it exists — and it exists only on a developer's machine (`DemoFirmSeeder` refuses testing and
 * production), so the Pest suite's seed never carries Alvorada's data (plan AM7). The platform firm
 * and firms made by `firm:create` get no invented data.
 *
 * Each firm's pass runs inside `CurrentFirm::runAs` (scoped reads see that firm; `DatabaseSeeder`
 * runs without model events, so a seeder still writes `firm_id` itself) and receives the firm's
 * ordinal: 0 for Franco, whose rows stay exactly as before, 1 for Alvorada, which a seeder uses to
 * vary its invented names and random seeds.
 */
trait SeedsEachDemoFirm
{
    /** The roles whose members work the board (the team), in their seeding order. */
    private const TEAM_ROLE_SLUG = 'equipe';

    /**
     * @param  callable(Firm, int): mixed  $seed  one firm's pass, given the firm and its ordinal
     * @param  string|null  $module  the opt-in module the data belongs to — a firm without it is skipped
     */
    protected function forEachDemoFirm(CurrentFirm $currentFirm, callable $seed, ?string $module = null): void
    {
        if (! DemoEnvironment::allows()) {
            return;
        }

        foreach ($this->demoFirms() as $ordinal => $firm) {
            if ($module !== null && ! FirmModules::isEnabled($firm, $module)) {
                continue;
            }

            $currentFirm->runAs($firm, fn () => $seed($firm, $ordinal));
        }
    }

    /** @return Collection<int, Firm> Franco first, then Alvorada when seeded */
    protected function demoFirms(): Collection
    {
        return Firm::query()
            ->whereIn('slug', [FirmSeeder::FRANCO_SLUG, DemoFirmSeeder::DEMO_SLUG])
            ->orderBy('id')
            ->get()
            ->values();
    }

    /**
     * The firm's team members who work leads, oldest first, at most `$limit` (Franco: Patrícia,
     * Mariana, Carlos — the rnc-1 board's three).
     *
     * @return Collection<int, User>
     */
    protected function teamOf(Firm $firm, int $limit): Collection
    {
        return User::query()
            ->where('firm_id', $firm->id)
            ->whereHas('role', fn ($query) => $query->where('slug', self::TEAM_ROLE_SLUG))
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->values();
    }

    /** The firm's manager (role `gestao`), oldest first — Franco's Reinaldo, Alvorada's Helena. */
    protected function managerOf(Firm $firm): ?User
    {
        return User::query()
            ->where('firm_id', $firm->id)
            ->whereHas('role', fn ($query) => $query->where('slug', DemoFirmSeeder::MANAGER_ROLE_SLUG))
            ->orderBy('id')
            ->first();
    }
}

<?php

namespace App\Modules\Platform\Repositories;

use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Scopes\FirmScope;
use App\Modules\Lead\Models\Lead;

/**
 * The console's reads across firms (rnc-4 R25). `FirmScope` is lifted here and nowhere else in the
 * module: the lead counts are every firm's by design, and they are counts — no row leaves this class.
 * A firm itself is not firm-scoped; one named by id is looked up by that id alone.
 */
class PlatformFirmRepository
{
    private const LEAD_COUNT = 'lead_count';

    /** @return list<Firm> the platform firm first, then by name */
    public function findAllFirms(): array
    {
        return Firm::query()->orderByDesc('is_platform')->orderBy('name')->get()->all();
    }

    public function findFirmById(int $id): ?Firm
    {
        return Firm::query()->find($id);
    }

    /**
     * Every firm's lead count in one grouped query.
     *
     * @return array<int, int> firm id → leads; a firm with none is absent
     */
    public function countLeadsByFirm(): array
    {
        return Lead::query()
            ->withoutGlobalScope(FirmScope::class)
            ->selectRaw('firm_id, count(*) as '.self::LEAD_COUNT)
            ->groupBy('firm_id')
            ->pluck(self::LEAD_COUNT, 'firm_id')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    public function countLeadsOfFirm(int $firmId): int
    {
        return Lead::query()->withoutGlobalScope(FirmScope::class)->where('firm_id', $firmId)->count();
    }
}

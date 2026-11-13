<?php

namespace App\Modules\Partner\Services;

use App\Modules\Diagnostic\DTOs\DiagnosticProfileDTO;
use App\Modules\Diagnostic\Services\RulesEngine;
use App\Modules\Lead\Models\Lead;
use App\Modules\Partner\Models\Partner;
use Illuminate\Support\Collection;

/**
 * The routing score (rnc-1 R22, the demo's `matchPartners()`, plan Defaults): a partner whose
 * specialty covers items of this lead's diagnostic starts at 70 for one and gains 11 per extra,
 * capped at 97; one that covers nothing sits low, between 12 and 28 — a number fixed per partner and
 * lead (a hash, not a random draw), so the list does not reshuffle between two visits.
 */
class PartnerMatcher
{
    public const COVERED_BASE = 70;

    public const PER_EXTRA_ITEM = 11;

    public const CAP = 97;

    public const UNCOVERED_FLOOR = 12;

    /** 12 + (0…16) = 12…28. */
    private const UNCOVERED_SPREAD = 17;

    public function __construct(private RulesEngine $rulesEngine) {}

    /**
     * @param  Collection<int, Partner>  $partners
     * @return list<array{partner: Partner, score: int, covers: list<array{id: string, title: string}>}> best first
     */
    public function match(Lead $lead, Collection $partners): array
    {
        $items = $this->rulesEngine->diagnose(DiagnosticProfileDTO::from($lead->profile))['items'];

        return $partners
            ->map(function (Partner $partner) use ($lead, $items) {
                $solved = array_map(fn ($rule) => $rule->value, $partner->specialtyCase()->covers());
                $covers = array_values(array_map(
                    fn (array $item) => ['id' => $item['id'], 'title' => $item['title']],
                    array_filter($items, fn (array $item) => in_array($item['id'], $solved, true)),
                ));

                return ['partner' => $partner, 'score' => $this->score($partner, $lead, count($covers)), 'covers' => $covers];
            })
            ->sort(fn (array $first, array $second) => [$second['score'], $first['partner']->name] <=> [$first['score'], $second['partner']->name])
            ->values()
            ->all();
    }

    public function score(Partner $partner, Lead $lead, int $coveredItems): int
    {
        if ($coveredItems === 0) {
            return self::UNCOVERED_FLOOR + crc32($partner->id.':'.$lead->id) % self::UNCOVERED_SPREAD;
        }

        return min(self::CAP, self::COVERED_BASE + self::PER_EXTRA_ITEM * ($coveredItems - 1));
    }
}

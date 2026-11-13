<?php

namespace App\Modules\Portfolio\Services;

use App\Modules\Diagnostic\DTOs\DiagnosticProfileDTO;
use App\Modules\Diagnostic\Enums\DiagnosticKind;
use App\Modules\Diagnostic\Services\RulesEngine;

/**
 * The fee repricing rule (rnc-1 R26, the demo's `repricing()`, W32/W43 of battonidev-1). Pure.
 *
 * The rules engine runs on the client's profile; each opportunity weighs 1,5 points and each risk
 * 2,5 (a risk needs follow-up, not only advice). The sum is the uplift in percent, kept in 4–25 %.
 * The new fee rounds to R$ 50 and stays inside 4–25 % of today's: floored to R$ 50 when the rounding
 * passes the top, ceiled when it falls under the bottom. `pct` is read from the ROUNDED fee, so the
 * letter, the list and the mark all say what the client will really pay.
 */
class RepricingCalculator
{
    public const MIN_PCT = 4;

    public const MAX_PCT = 25;

    private const WEIGHTS = [DiagnosticKind::Opportunity->value => 1.5, DiagnosticKind::Risk->value => 2.5];

    private const ROUNDING = 50;

    private const MONTHS_IN_YEAR = 12;

    public function __construct(private RulesEngine $rulesEngine) {}

    /**
     * @return array{items: list<array{id: string, kind: string, title: string, rule: string, detail: string, min: float, max: float}>, opportunities: int, risks: int, current: float, suggested: float, pct: int, uplift: float}
     */
    public function quote(DiagnosticProfileDTO $profile, float $currentFee): array
    {
        $diagnosis = $this->rulesEngine->diagnose($profile);
        $raw = array_sum(array_map(fn (array $item) => self::WEIGHTS[$item['kind']], $diagnosis['items']));
        $target = min(self::MAX_PCT, max(self::MIN_PCT, $raw));
        $suggested = $currentFee > 0 ? self::suggestedFee($currentFee, $target) : 0.0;

        return [
            'items' => $diagnosis['items'],
            'opportunities' => $diagnosis['opportunities'],
            'risks' => $diagnosis['risks'],
            'current' => $currentFee,
            'suggested' => $suggested,
            'pct' => $currentFee > 0 ? (int) round(($suggested / $currentFee - 1) * 100) : 0,
            'uplift' => round(($suggested - $currentFee) * self::MONTHS_IN_YEAR, 2),
        ];
    }

    /** Today's fee raised by `$targetPct`, rounded to R$ 50 and kept inside 4–25 %. */
    public static function suggestedFee(float $currentFee, float $targetPct): float
    {
        $lowest = $currentFee * (1 + self::MIN_PCT / 100);
        $highest = $currentFee * (1 + self::MAX_PCT / 100);
        $suggested = round($currentFee * (1 + $targetPct / 100) / self::ROUNDING) * self::ROUNDING;

        if ($suggested > $highest) {
            $suggested = floor($highest / self::ROUNDING) * self::ROUNDING;
        }

        if ($suggested < $lowest) {
            $suggested = ceil($lowest / self::ROUNDING) * self::ROUNDING;
        }

        return (float) $suggested;
    }
}

<?php

namespace App\Modules\Diagnostic\Services;

use App\Modules\Diagnostic\DTOs\DiagnosticProfileDTO;
use App\Modules\Diagnostic\Enums\DiagnosticKind;
use App\Modules\Diagnostic\Enums\DiagnosticRule;
use App\Modules\Diagnostic\Enums\Regime;
use App\Modules\Diagnostic\Enums\RevenueBand;
use App\Modules\Diagnostic\Enums\Sector;
use App\Modules\Diagnostic\Enums\Tax;

/**
 * The Reforma rules engine (rnc-1 R12, R14) — the demo's `diagnose()`, `taxMix()`,
 * `splitPaymentRange()` and `formatRange()` ported to PHP. Pure: no database, no request.
 *
 * The simulator's rates are nominal references on revenue (facts.md §2): PIS/Cofins out in 2027,
 * ICMS and ISS at 9/10 … 6/10 in 2029–2032 and gone in 2033, CBS at the TCU 2027 reference (11,3 %,
 * minus 0,1 pp in 2027–28), IBS rising to the rest of the ~26,5 % combined estimate. Every figure is
 * shown as "estimativa".
 */
class RulesEngine
{
    private const COMBINED_RATE = 26.5;

    private const CBS_FULL = 11.3;

    private const IBS_FULL = self::COMBINED_RATE - self::CBS_FULL;

    /** 2026 is the test year: CBS 0,9 % and IBS 0,1 %, offset against PIS/Cofins. */
    private const TEST_YEAR = 2026;

    private const TEST_YEAR_CBS = 0.9;

    private const TEST_YEAR_IBS = 0.1;

    /** In 2027–28 the CBS is 0,1 pp under its full rate and the IBS stays at its 0,1 % test rate. */
    private const LAST_REDUCED_CBS_YEAR = 2028;

    private const CBS_REDUCTION = 0.1;

    /** The share of today's ICMS/ISS still charged each year (and so of the IBS not yet charged). */
    private const LEGACY_SHARE_BY_YEAR = [2026 => 1.0, 2027 => 1.0, 2028 => 1.0, 2029 => 0.9, 2030 => 0.8, 2031 => 0.7, 2032 => 0.6, 2033 => 0.0];

    private const PIS_COFINS_REAL = 9.25;

    private const PIS_COFINS_PRESUMIDO = 3.65;

    private const ICMS_RATE = 18.0;

    private const ISS_RATE = 5.0;

    /** Saúde and educação pay 40 % of the IBS/CBS rate. */
    private const REDUCED_RATE_FACTOR = 0.4;

    private const MONTHS_IN_YEAR = 12;

    /** Net of credits, the split payment holds 40–70 % of a month of gross IBS/CBS. */
    private const SPLIT_PAYMENT_SHARE = [0.4, 0.7];

    private const THOUSAND = 1_000;

    private const MILLION = 1_000_000;

    /**
     * The opportunities and risks of a profile, each with its R$ range. Empty until sector, regime and
     * band are all known.
     *
     * @return array{items: list<array{id: string, kind: string, title: string, rule: string, detail: string, min: float, max: float}>, opportunities: int, risks: int}
     */
    public function diagnose(DiagnosticProfileDTO $profile): array
    {
        $band = RevenueBand::tryFrom((string) $profile->band);

        if (! $profile->isComplete() || ! $band) {
            return ['items' => [], 'opportunities' => 0, 'risks' => 0];
        }

        $items = [];

        foreach (DiagnosticRule::cases() as $rule) {
            if (! $rule->appliesTo($profile)) {
                continue;
            }

            [$low, $high] = $rule->share();

            $items[] = [
                'id' => $rule->value,
                'kind' => $rule->kind()->value,
                'title' => $rule->title(),
                'rule' => $rule->citation(),
                'detail' => $rule->detail(),
                'min' => $band->revenue() * $low,
                'max' => $band->revenue() * $high,
            ];
        }

        $opportunities = count(array_filter($items, fn (array $item) => $item['kind'] === DiagnosticKind::Opportunity->value));

        return ['items' => $items, 'opportunities' => $opportunities, 'risks' => count($items) - $opportunities];
    }

    /**
     * The 2026–2033 tax mix on revenue, one entry per year.
     *
     * @return list<array{year: int, parts: list<array{tax: string, label: string, rate: float}>, total: float}>
     */
    public function taxMix(DiagnosticProfileDTO $profile): array
    {
        $sector = Sector::tryFrom((string) $profile->sector);
        $pisCofins = $profile->regime === Regime::Real->value ? self::PIS_COFINS_REAL : self::PIS_COFINS_PRESUMIDO;
        $icms = $sector?->chargesIcms() ? self::ICMS_RATE : 0.0;
        $iss = $icms > 0 ? 0.0 : self::ISS_RATE;
        $factor = $sector?->hasReducedRate() ? self::REDUCED_RATE_FACTOR : 1.0;
        $years = [];

        foreach (self::LEGACY_SHARE_BY_YEAR as $year => $legacyShare) {
            $rates = [
                Tax::PisCofins->value => $year === self::TEST_YEAR ? $pisCofins : 0.0,
                Tax::Icms->value => $icms * $legacyShare,
                Tax::Iss->value => $iss * $legacyShare,
                Tax::Cbs->value => $factor * $this->cbsRate($year),
                Tax::Ibs->value => $factor * $this->ibsRate($year, $legacyShare),
            ];
            $parts = [];

            foreach (Tax::cases() as $tax) {
                if ($rates[$tax->value] <= 0) {
                    continue;
                }

                $parts[] = ['tax' => $tax->value, 'label' => $tax->label(), 'rate' => round($rates[$tax->value], 1)];
            }

            $years[] = ['year' => $year, 'parts' => $parts, 'total' => round(array_sum(array_column($parts, 'rate')), 1)];
        }

        return $years;
    }

    /**
     * The split payment's cash effect (facts.md §2, arts. 31–33): IBS/CBS leave at settlement
     * instead of on the monthly due date. Per month, low and high.
     *
     * @return array{0: float, 1: float}
     */
    public function splitPaymentRange(DiagnosticProfileDTO $profile): array
    {
        $band = RevenueBand::tryFrom((string) $profile->band) ?? RevenueBand::UpTo360Thousand;
        $factor = Sector::tryFrom((string) $profile->sector)?->hasReducedRate() ? self::REDUCED_RATE_FACTOR : 1.0;
        $monthlyTax = ($band->revenue() / self::MONTHS_IN_YEAR) * (self::COMBINED_RATE / 100) * $factor;

        return [$monthlyTax * self::SPLIT_PAYMENT_SHARE[0], $monthlyTax * self::SPLIT_PAYMENT_SHARE[1]];
    }

    /**
     * `[18_000, 42_000]` → "R$ 18–42 mil/ano"; from a million up → "R$ 1,2–3,4 mi/ano". Never below
     * R$ 1 mil, and the high end always above the low one.
     */
    public static function formatRange(float $low, float $high, string $unit = 'ano'): string
    {
        if ($high >= self::MILLION) {
            return sprintf('R$ %s–%s mi/%s', self::decimal($low / self::MILLION, 1), self::decimal($high / self::MILLION, 1), $unit);
        }

        $lowThousands = max(1, (int) round($low / self::THOUSAND));
        $highThousands = max($lowThousands + 1, (int) round($high / self::THOUSAND));

        return sprintf('R$ %s–%s mil/%s', self::decimal($lowThousands, 0), self::decimal($highThousands, 0), $unit);
    }

    private function cbsRate(int $year): float
    {
        if ($year === self::TEST_YEAR) {
            return self::TEST_YEAR_CBS;
        }

        return $year <= self::LAST_REDUCED_CBS_YEAR ? self::CBS_FULL - self::CBS_REDUCTION : self::CBS_FULL;
    }

    private function ibsRate(int $year, float $legacyShare): float
    {
        return $year <= self::LAST_REDUCED_CBS_YEAR ? self::TEST_YEAR_IBS : self::IBS_FULL * (1 - $legacyShare);
    }

    /** pt-BR digits: "1.234,5". */
    private static function decimal(float $value, int $decimals): string
    {
        return number_format($value, $decimals, ',', '.');
    }
}

<?php

namespace App\Modules\Briefing\Helpers;

/**
 * The pipeline's annual fee range (rnc-3 R24, D12, AM7): a lead has no fee yet, so it borrows the
 * portfolio's — the monthly fee quartiles (p25–p75) of the clients in its band, × 12. A band of
 * fewer than `MIN_BAND_CLIENTS` clients, or a lead without a band, borrows every client's
 * quartiles; an empty portfolio gives no range at all ("sem base na carteira").
 *
 * Nearest-rank on the cents: p25 is the ⌈n/4⌉-th fee in order, p75 the ⌈3n/4⌉-th — always a fee
 * some client pays, never an interpolation. Pure: the caller hands it the fees by band.
 */
class FeeQuartiles
{
    public const MIN_BAND_CLIENTS = 5;

    public const MONTHS = 12;

    private const CENTS_PER_UNIT = 100;

    /** @var array<string, list<int>> band → its clients' monthly fees in cents, ascending */
    private array $byBand = [];

    /** @var list<int> every client's monthly fee in cents, ascending */
    private array $all = [];

    /** @param  array<string, list<int>>  $feesByBand  band → monthly fees in cents, any order */
    public function __construct(array $feesByBand)
    {
        foreach ($feesByBand as $band => $fees) {
            sort($fees);
            $this->byBand[$band] = $fees;
            array_push($this->all, ...$fees);
        }

        sort($this->all);
    }

    /** @return array{0: int, 1: int}|null the band's monthly [p25, p75] in cents; null on an empty portfolio */
    public function monthlyFor(?string $band): ?array
    {
        $fees = $band !== null && count($this->byBand[$band] ?? []) >= self::MIN_BAND_CLIENTS ? $this->byBand[$band] : $this->all;

        return $fees === [] ? null : self::quartiles($fees);
    }

    /**
     * Σ of each lead's band quartiles × 12 — `[0, 0]` for no lead, null on an empty portfolio.
     *
     * @param  list<string|null>  $bands  one per lead
     * @return array{0: int, 1: int}|null
     */
    public function annualRange(array $bands): ?array
    {
        if ($this->all === []) {
            return null;
        }

        $range = [0, 0];

        foreach ($bands as $band) {
            [$low, $high] = $this->monthlyFor($band) ?? [0, 0];
            $range = [$range[0] + $low * self::MONTHS, $range[1] + $high * self::MONTHS];
        }

        return $range;
    }

    /**
     * `clients.monthly_fee` (a decimal(10,2)) in cents, read as text: "3000.00", "1234.5", "900".
     * SQLite hands a whole number back as an int and a fraction as a real; both go through their
     * shortest text, which for two decimals is exact.
     */
    public static function cents(string|int|float $decimal): int
    {
        $text = is_float($decimal) ? rtrim(rtrim(sprintf('%.2F', $decimal), '0'), '.') : trim((string) $decimal);
        [$whole, $fraction] = array_pad(explode('.', $text, 2), 2, '');

        return (int) $whole * self::CENTS_PER_UNIT + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    /**
     * @param  non-empty-list<int>  $sorted
     * @return array{0: int, 1: int}
     */
    private static function quartiles(array $sorted): array
    {
        $n = count($sorted);

        return [$sorted[intdiv($n + 3, 4) - 1], $sorted[intdiv(3 * $n + 3, 4) - 1]];
    }
}

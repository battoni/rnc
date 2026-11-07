<?php

namespace App\Modules\Partner\Helpers;

/**
 * A client contract's annual value as a partner may see it (rnc-3 AM20, R21): one of six fixed bands,
 * the lower bound included and the upper excluded — never the value itself, which is the client's
 * price and Franco's to keep. Pure.
 */
final class ContractValueBand
{
    /** The bands' lower bounds in cents; the last has no upper bound. */
    private const BOUNDS = [0, 10_000_000, 25_000_000, 50_000_000, 100_000_000, 200_000_000];

    private const MILLION_CENTS = 100_000_000;

    private const THOUSAND_CENTS = 100_000;

    /** @return array{min_cents: int, max_cents: int|null, label: string} */
    public static function of(int $cents): array
    {
        $index = 0;

        foreach (self::BOUNDS as $position => $bound) {
            if ($cents >= $bound) {
                $index = $position;
            }
        }

        $min = self::BOUNDS[$index];
        $max = self::BOUNDS[$index + 1] ?? null;

        return ['min_cents' => $min, 'max_cents' => $max, 'label' => self::label($min, $max)];
    }

    /** "até R$ 100 mil", "R$ 500 mil a R$ 1 mi", "acima de R$ 2 mi" — pt-BR, as the portal and the message read. */
    private static function label(int $min, ?int $max): string
    {
        if ($max === null) {
            return 'acima de '.self::money($min);
        }

        return $min === 0 ? 'até '.self::money($max) : self::money($min).' a '.self::money($max);
    }

    private static function money(int $cents): string
    {
        return $cents >= self::MILLION_CENTS
            ? 'R$ '.intdiv($cents, self::MILLION_CENTS).' mi'
            : 'R$ '.intdiv($cents, self::THOUSAND_CENTS).' mil';
    }
}

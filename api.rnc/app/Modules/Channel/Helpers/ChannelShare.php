<?php

namespace App\Modules\Channel\Helpers;

/**
 * Franco's share of an RNC firm's monthly fee (rnc-3 R8, plan P5): integer cents, a rate in basis
 * points, rounded half-up PER FIRM — `intdiv(fee × bp + 5000, 10000)` — and only then summed, so a
 * total always equals the rows it is shown over. Pure: no database, no config.
 */
final class ChannelShare
{
    private const BASIS = 10000;

    private const HALF = 5000;

    public static function of(int $feeCents, int $shareBp): int
    {
        return intdiv($feeCents * $shareBp + self::HALF, self::BASIS);
    }

    /**
     * @param  iterable<array{0: int, 1: int, 2?: int}>  $firms  `[fee_cents, share_bp]`, or with a third
     *                                                           element, that many firms alike
     */
    public static function sum(iterable $firms): int
    {
        $total = 0;

        foreach ($firms as $firm) {
            $total += self::of($firm[0], $firm[1]) * ($firm[2] ?? 1);
        }

        return $total;
    }
}

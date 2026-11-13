<?php

namespace App\Modules\Fiscal\Enums;

/**
 * A client's NF-e readiness (rnc-2 R18, demo `nfeStatus`, thresholds 95/80 — plan Defaults):
 * crítico under 80 % complete or with any invoice at risk of rejection 1115, ok at 95 % or more,
 * else atenção; a client with no issued invoice in the window is `sem_notas`. Compared in integers
 * (complete × 100 against threshold × invoices), so 95 % is ok and 94,9 % never rounds into it.
 */
enum ReadinessStatus: string
{
    case Critico = 'critico';
    case Atencao = 'atencao';
    case Ok = 'ok';
    case SemNotas = 'sem_notas';

    public const OK_PCT = 95;

    public const WARN_PCT = 80;

    public static function fromCounts(int $invoices, int $complete, int $atRisk): self
    {
        if ($invoices === 0) {
            return self::SemNotas;
        }

        $belowWarning = $complete * 100 < self::WARN_PCT * $invoices;

        if ($belowWarning || $atRisk > 0) {
            return self::Critico;
        }

        return $complete * 100 >= self::OK_PCT * $invoices ? self::Ok : self::Atencao;
    }

    /** The list's order: who needs a warning first, `sem_notas` last (demo `NFE_ORDER`). */
    public function order(): int
    {
        return match ($this) {
            self::Critico => 0,
            self::Atencao => 1,
            self::Ok => 2,
            self::SemNotas => 3,
        };
    }
}

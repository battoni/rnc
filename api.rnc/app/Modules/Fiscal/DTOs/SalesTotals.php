<?php

namespace App\Modules\Fiscal\DTOs;

/**
 * One client's issued invoices (saída) of the Radar's window (rnc-2 R25, D9, plan AM6): how many,
 * their value and the value sold to a CNPJ in the 90 days, in cents; and, of the last 30 days, the
 * readiness counts (`recent*`: invoices, complete — every item passing the three checks, spec C4 —
 * and at risk of rejection 1115).
 */
final readonly class SalesTotals
{
    public function __construct(
        public int $clientId,
        public int $documents,
        public int $salesCents,
        public int $b2bCents,
        public int $recentInvoices,
        public int $recentComplete,
        public int $recentAtRisk,
    ) {}
}

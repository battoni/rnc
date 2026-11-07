<?php

namespace App\Modules\Fiscal\DTOs;

/**
 * One client's purchases of the window (rnc-2 R21, plan AM6): documents, their value and the value
 * bought from Simples/MEI suppliers, in cents.
 */
final readonly class PurchaseTotals
{
    public function __construct(
        public int $clientId,
        public int $documents,
        public int $purchasesCents,
        public int $simplesCents,
    ) {}
}

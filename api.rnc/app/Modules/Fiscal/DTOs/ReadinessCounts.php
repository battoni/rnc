<?php

namespace App\Modules\Fiscal\DTOs;

/**
 * One group of the readiness aggregate (rnc-2 R18, plan AM6): a client's issued invoices of the
 * window from one emitting system — how many, how many complete, how many at risk of 1115.
 */
final readonly class ReadinessCounts
{
    public function __construct(
        public int $clientId,
        public ?string $system,
        public int $invoices,
        public int $complete,
        public int $atRisk,
    ) {}
}

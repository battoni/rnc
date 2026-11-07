<?php

namespace App\Modules\Fiscal\DTOs;

use Carbon\CarbonImmutable;

/**
 * One issued invoice as the Radar's proof names it (rnc-2 R25): its access key, whether it went to a
 * company (a CNPJ), when it was issued and whether any item failed a readiness check.
 */
final readonly class SalesKey
{
    public function __construct(
        public int $clientId,
        public string $accessKey,
        public bool $toCompany,
        public CarbonImmutable $issuedAt,
        public bool $incomplete,
    ) {}
}

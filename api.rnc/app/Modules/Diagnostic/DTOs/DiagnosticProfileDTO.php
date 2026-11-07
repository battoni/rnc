<?php

namespace App\Modules\Diagnostic\DTOs;

use Spatie\LaravelData\Data;

/**
 * The answers the rules engine reads (rnc-1 R10): sector, regime, faturamento band, the share of
 * sales to companies (0–100), ICMS incentives and exports. Every field may still be unanswered.
 */
class DiagnosticProfileDTO extends Data
{
    public function __construct(
        public ?string $sector = null,
        public ?string $regime = null,
        public ?string $band = null,
        public ?int $b2b = null,
        public bool $incentivos = false,
        public bool $exporta = false,
    ) {}

    /** What the result needs: without a sector, a regime and a band there is nothing to diagnose. */
    public function isComplete(): bool
    {
        return $this->sector !== null && $this->regime !== null && $this->band !== null;
    }
}

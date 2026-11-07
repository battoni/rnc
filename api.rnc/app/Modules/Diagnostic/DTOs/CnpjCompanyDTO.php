<?php

namespace App\Modules\Diagnostic\DTOs;

use Spatie\LaravelData\Data;

/**
 * What a CNPJ lookup found (rnc-1 R9, plan AM10). `band` and `regime` are null when the public
 * registry cannot tell them, and the quiz asks.
 */
class CnpjCompanyDTO extends Data
{
    public function __construct(
        public string $cnpj,
        public string $name,
        public string $cnae,
        public ?string $cnae_description,
        public ?string $porte,
        public string $sector,
        public ?string $band,
        public ?string $regime,
    ) {}
}

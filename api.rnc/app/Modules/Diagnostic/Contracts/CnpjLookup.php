<?php

namespace App\Modules\Diagnostic\Contracts;

use App\Modules\Diagnostic\DTOs\CnpjCompanyDTO;
use App\Modules\Diagnostic\Services\BrasilApiCnpjLookup;
use Illuminate\Container\Attributes\Bind;

/**
 * Reads a company from the public registry by its CNPJ (rnc-1 R9, D3). Null for an unknown CNPJ
 * and for any failure — a timeout, a server error — so the diagnostic falls back to the quiz and
 * never shows an error page. The CNPJ arrives already checked: 14 digits, valid check digits.
 *
 * Bound to BrasilAPI; a test or another provider swaps the binding, never the callers.
 */
#[Bind(BrasilApiCnpjLookup::class)]
interface CnpjLookup
{
    public function lookup(string $cnpj): ?CnpjCompanyDTO;
}

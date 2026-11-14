<?php

namespace App\Modules\Firm\Services;

use App\Modules\Firm\Models\Firm;
use Illuminate\Http\Request;

/**
 * The firm the platform operator is looking at through "Acessar como suporte" (rnc-4 R26, D20).
 *
 * `ResolveCurrentFirm` asks this first for a signed-in person with no firm, and falls back to the
 * platform firm (plan AM1). View-as arrives in F4c; until then nobody is viewing as anyone, and
 * the operator reaches another firm only through the platform console.
 */
class ViewAsFirm
{
    public function resolve(Request $request): ?Firm
    {
        return null;
    }
}

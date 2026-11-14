<?php

namespace App\Modules\Firm\Services;

use App\Modules\Firm\Models\Firm;
use App\Modules\Platform\Services\ViewAsService;
use Illuminate\Http\Request;

/**
 * The firm the platform operator is looking at through "Acessar como suporte" (rnc-4 R26, D20).
 *
 * `ResolveCurrentFirm` asks this first for a signed-in person with no firm, and falls back to the
 * platform firm (plan AM1); `auth/me` answers it as `platform.view_as`. The session and its audit
 * row are the Platform module's (`ViewAsService::current`): only the firm-less `operador` whose open
 * row the session names gets a firm here, and never the platform firm.
 */
class ViewAsFirm
{
    public function __construct(private ViewAsService $viewAsService) {}

    public function resolve(Request $request): ?Firm
    {
        return $this->viewAsService->current($request)?->firm;
    }
}

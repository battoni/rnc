<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Modules\Firm\Helpers\FirmModules;
use App\Modules\Firm\Services\CurrentFirm;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A module's routes answer only for a firm that has it on (rnc-4 R17) — the route alias `module`,
 * `module:<key>[,<key>…]`, every key needed (`module:radar,partners` on the Radar's batch
 * referral). Off is 404 `modules.disabled`: to the outside the route is not there.
 *
 * It reads the firm `ResolveCurrentFirm` already set (the signed-in person's, or the visitor's
 * host), so it runs on route middleware only, after the api group.
 */
class EnsureModuleEnabled
{
    public const ALIAS = 'module';

    public function __construct(private CurrentFirm $currentFirm) {}

    public function handle(Request $request, Closure $next, string ...$modules): Response
    {
        foreach ($modules as $module) {
            if (! $this->currentFirm->hasModule($module)) {
                return ApiResponse::notFound(message: FirmModules::DISABLED);
            }
        }

        return $next($request);
    }
}

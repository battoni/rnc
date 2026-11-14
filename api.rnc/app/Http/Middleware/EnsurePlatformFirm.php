<?php

namespace App\Http\Middleware;

use App\Modules\Firm\Services\CurrentFirm;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A route of the platform's own site (rnc-4 R29: the beta signup) answers only on the platform firm
 * — the root host, header-less (AM9) — and 404 on any tenant, before its body is validated: a firm
 * host never learns the route exists. Runs after `ResolveCurrentFirm` (api group).
 */
class EnsurePlatformFirm
{
    public const ALIAS = 'platform.firm';

    public function __construct(private CurrentFirm $currentFirm) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) $this->currentFirm->get()?->is_platform, Response::HTTP_NOT_FOUND);

        return $next($request);
    }
}

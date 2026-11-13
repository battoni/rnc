<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Modules\Firm\Repositories\FirmRepository;
use App\Modules\Firm\Services\CurrentFirm;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decides the firm of this request before any firm-scoped query runs (D1, plan AM6).
 *
 * A signed-in person belongs to one firm and sees only it: the `X-Firm` header cannot move them,
 * or a header would be a way into another firm's leads. A visitor of the public site has no
 * account, so the site says which firm it is with `X-Firm: <slug>`; without the header the
 * request belongs to the default firm (`franco`). A header naming a firm that does not exist is
 * a 404 — the same calm "not found" every other unknown thing gets.
 *
 * When neither resolves (a database with no firm yet), nothing is set and `FirmScope` reads
 * nothing: the closed answer, not everybody's rows.
 */
class ResolveCurrentFirm
{
    public const HEADER = 'X-Firm';

    public function __construct(
        private CurrentFirm $currentFirm,
        private FirmRepository $firmRepository,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $firmId = $request->user()?->getAttribute('firm_id');

        if ($firmId !== null) {
            $this->currentFirm->set($this->firmRepository->findFirmById((int) $firmId));

            return $next($request);
        }

        $requestedSlug = $request->header(self::HEADER);
        $firm = $this->firmRepository->findFirmBySlug($requestedSlug ?? (string) config('firms.default_slug'));

        if ($requestedSlug !== null && ! $firm) {
            return ApiResponse::notFound(message: 'firms.notFound');
        }

        $this->currentFirm->set($firm);

        return $next($request);
    }
}

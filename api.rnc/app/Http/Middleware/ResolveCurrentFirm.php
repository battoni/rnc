<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Modules\Firm\Repositories\FirmRepository;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Firm\Services\FirmResolver;
use App\Modules\Firm\Services\ViewAsFirm;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decides the firm of this request before any firm-scoped query runs (D1, plan AM6; rnc-4 R2–R5).
 *
 * A signed-in person belongs to one firm and sees only it: the `X-Firm` header never moves them.
 * A header naming ANOTHER firm is refused with 403 `firms.mismatch` (rnc-4 D5) — the SPA always
 * sends it on a firm host, so a mismatch means a session that wandered onto the wrong host, and
 * the SPA signs out locally. A request without the header keeps the person's own firm, which is
 * what lets the suite act as firm-B users with no header.
 *
 * A signed-in person of an offboarded firm (rnc-4 R40, `firm:offboard`) is refused 403
 * `firms.offboarded` and signed out: a session opened before the exit ends there.
 *
 * A signed-in person with no firm (the platform operator) works in the firm they are viewing as
 * (F4c), else the platform firm — never in no firm, never in the header's (plan AM1). Without a
 * platform firm they are refused with 403 `firms.required`: a null firm here was a 500 further on.
 *
 * A visitor names the firm with `X-Firm: <slug>`; a reserved, unknown or platform slug is a 404
 * `firms.notFound` (D2, AM9). Without the header the request belongs to the default firm — the
 * platform firm at the root (`DEFAULT_FIRM_SLUG`, D6). When that does not resolve either (a
 * database with no firm yet), nothing is set and `FirmScope` reads nothing.
 */
class ResolveCurrentFirm
{
    public const HEADER = 'X-Firm';

    public const MISMATCH = 'firms.mismatch';

    public const REQUIRED = 'firms.required';

    public const NOT_FOUND = 'firms.notFound';

    public const OFFBOARDED = 'firms.offboarded';

    public function __construct(
        private CurrentFirm $currentFirm,
        private FirmRepository $firmRepository,
        private FirmResolver $firmResolver,
        private ViewAsFirm $viewAsFirm,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $requestedSlug = $request->header(self::HEADER);

        if ($user !== null) {
            return $this->resolveSignedIn($request, $next, $requestedSlug);
        }

        $firm = $requestedSlug !== null
            ? $this->firmResolver->findTenantBySlug($requestedSlug)
            : $this->firmResolver->findDefaultFirm();

        if ($requestedSlug !== null && ! $firm) {
            return ApiResponse::notFound(message: self::NOT_FOUND);
        }

        $this->currentFirm->set($firm);

        return $next($request);
    }

    private function endOffboardedSession(Request $request): Response
    {
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return ApiResponse::forbidden(message: self::OFFBOARDED);
    }

    private function resolveSignedIn(Request $request, Closure $next, ?string $requestedSlug): Response
    {
        $firmId = $request->user()?->getAttribute('firm_id');

        if ($firmId !== null) {
            $firm = $this->firmRepository->findFirmById((int) $firmId);

            if ($requestedSlug !== null && $requestedSlug !== $firm?->slug) {
                return ApiResponse::forbidden(message: self::MISMATCH);
            }

            if ($firm?->isOffboarded()) {
                return $this->endOffboardedSession($request);
            }

            $this->currentFirm->set($firm);

            return $next($request);
        }

        if ($requestedSlug !== null) {
            return ApiResponse::forbidden(message: self::MISMATCH);
        }

        $firm = $this->viewAsFirm->resolve($request) ?? $this->firmRepository->findPlatformFirm();

        if (! $firm) {
            return ApiResponse::forbidden(message: self::REQUIRED);
        }

        $this->currentFirm->set($firm);

        return $next($request);
    }
}

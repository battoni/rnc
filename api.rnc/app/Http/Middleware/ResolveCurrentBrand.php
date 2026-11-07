<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Modules\Firm\Repositories\BrandRepository;
use App\Modules\Firm\Services\CurrentBrand;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the sub-brand of a public request (rnc-3 R2, D2) — the route alias `brand`, on the public
 * site's routes only, never on the back office, where the brand is irrelevant.
 *
 * The site sends `X-Brand: <slug>`; without it the request is the firm's main brand. The slug is
 * looked up through `FirmScope` under the `CurrentFirm` that `ResolveCurrentFirm` already set, so a
 * header can only name a brand of the request's own firm: another firm's slug is as unknown as a
 * typo. An unknown or inactive slug is 404 `brands.notFound`, which the SPA reads to drop the
 * stored brand — except under `brand:fallback` (`POST public/events`, AM21), where a stale tab's
 * beacon is recorded on the main brand instead of being lost.
 */
class ResolveCurrentBrand
{
    public const ALIAS = 'brand';

    public const HEADER = 'X-Brand';

    /** The route parameter that turns an unknown slug into the main brand. */
    public const FALLBACK = 'fallback';

    /** `firm_brands.slug`'s width: a longer header cannot name a brand. */
    private const SLUG_MAX_LENGTH = 40;

    public function __construct(
        private CurrentBrand $currentBrand,
        private BrandRepository $brandRepository,
    ) {}

    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $slug = trim((string) $request->header(self::HEADER));

        if ($slug === '') {
            return $next($request);
        }

        $brand = strlen($slug) > self::SLUG_MAX_LENGTH ? null : $this->brandRepository->findActiveBrandBySlug($slug);

        if (! $brand && $mode !== self::FALLBACK) {
            return ApiResponse::notFound(message: 'brands.notFound');
        }

        $this->currentBrand->set($brand);

        return $next($request);
    }
}

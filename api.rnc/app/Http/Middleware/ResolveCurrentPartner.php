<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Modules\Partner\Repositories\PartnerRepository;
use App\Modules\Partner\Services\CurrentPartner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the partner of a portal request (rnc-2 R4, D2; plan AM1) — the route alias `portal`, on
 * every `portal/*` route and nowhere else (never global).
 *
 * The Partner is the signed-in user's `partner_id`, loaded through `FirmScope` under the
 * `CurrentFirm` that `ResolveCurrentFirm` already set from the user's own firm — so a login pointing
 * at another firm's partner, whatever `X-Firm` it sends, finds none. No partner, or an inactive one,
 * is 403 `portal.noPartner`: the operator holds `portal.access` and is refused here.
 */
class ResolveCurrentPartner
{
    public const ALIAS = 'portal';

    public function __construct(
        private CurrentPartner $currentPartner,
        private PartnerRepository $partnerRepository,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $partnerId = $request->user()?->getAttribute('partner_id');
        $partner = $partnerId === null ? null : $this->partnerRepository->findActivePartnerById((int) $partnerId);

        if (! $partner) {
            return ApiResponse::forbidden(message: 'portal.noPartner');
        }

        $this->currentPartner->set($partner);

        return $next($request);
    }
}

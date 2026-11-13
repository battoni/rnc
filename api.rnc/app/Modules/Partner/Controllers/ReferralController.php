<?php

namespace App\Modules\Partner\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Partner\DTOs\ReferLeadDTO;
use App\Modules\Partner\Requests\ListReferralsRequest;
use App\Modules\Partner\Requests\ReferLeadRequest;
use App\Modules\Partner\Resources\PartnerMatchesResource;
use App\Modules\Partner\Resources\ReferralResource;
use App\Modules\Partner\Services\ReferralService;
use App\Modules\Pipeline\Resources\LeadEventResource;
use Illuminate\Http\JsonResponse;

/** Routing on the lead detail and the trail (rnc-1 R22–R23). Another firm's lead is a 404. */
class ReferralController extends Controller
{
    private const LEAD_NOT_FOUND = 'pipeline.notFound';

    public function __construct(private ReferralService $referralService) {}

    public function matches(int $id): JsonResponse
    {
        $matches = $this->referralService->matches($id);

        return ! $matches ? ApiResponse::notFound(message: self::LEAD_NOT_FOUND) : ApiResponse::success(data: new PartnerMatchesResource($matches));
    }

    public function store(ReferLeadRequest $request, int $id): JsonResponse
    {
        /** @var User $person */
        $person = $request->user();
        $written = $this->referralService->refer($id, ReferLeadDTO::from($request), $person);

        return ! $written ? ApiResponse::notFound(message: self::LEAD_NOT_FOUND) : ApiResponse::created(data: [
            'referral' => new ReferralResource($written['referral']),
            'event' => new LeadEventResource($written['event']),
        ]);
    }

    public function index(ListReferralsRequest $request): JsonResponse
    {
        return ApiResponse::paginated($this->referralService->trail($request->partnerId(), $request->perPage()), ReferralResource::class);
    }
}

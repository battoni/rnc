<?php

namespace App\Modules\Partner\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Partner\DTOs\ReferContractDTO;
use App\Modules\Partner\Requests\ReferContractRequest;
use App\Modules\Partner\Resources\LegalPartnerResource;
use App\Modules\Partner\Resources\ReferralResource;
use App\Modules\Partner\Services\ReferralService;
use Illuminate\Http\JsonResponse;

/**
 * "Encaminhar ao jurídico" on the contract alert (rnc-3 R20, D11), behind `contracts.manage`: the
 * legal partners' picker and the referral of one contract. Partner's, not Portfolio's — the referral
 * is a `referrals` row and Partner already reads Portfolio, never the other way round. Another firm's
 * contract is a 404, never a 403.
 */
class ContractReferralController extends Controller
{
    private const CONTRACT_NOT_FOUND = 'portfolio.contractNotFound';

    public function __construct(private ReferralService $referralService) {}

    public function partners(): JsonResponse
    {
        return ApiResponse::success(data: LegalPartnerResource::collection($this->referralService->legalPartners()));
    }

    public function store(ReferContractRequest $request, int $id): JsonResponse
    {
        /** @var User $person */
        $person = $request->user();
        $referral = $this->referralService->referContract($id, ReferContractDTO::from($request)->partner_id, $person);

        return ! $referral ? ApiResponse::notFound(message: self::CONTRACT_NOT_FOUND) : ApiResponse::created(data: new ReferralResource($referral));
    }
}

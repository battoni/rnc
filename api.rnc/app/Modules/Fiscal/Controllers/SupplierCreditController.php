<?php

namespace App\Modules\Fiscal\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Fiscal\DTOs\SupplierCreditFilterDTO;
use App\Modules\Fiscal\Requests\SupplierCreditIndexRequest;
use App\Modules\Fiscal\Resources\SupplierCreditClientResource;
use App\Modules\Fiscal\Services\SupplierCreditService;
use App\Modules\Portfolio\Resources\ClientEventResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Crédito de fornecedores" (rnc-2 R21–R22), behind `fiscal.read` (equipe, ceo) — the review too,
 * on purpose (plan AM13, Fiscal RULES.md). Another firm's client, an unknown id and a Simples client
 * are a 404; nothing bought from Simples is a 422 `no_simples_purchases` (`FiscalException`).
 */
class SupplierCreditController extends Controller
{
    private const NOT_FOUND = 'fiscal.notFound';

    public function __construct(private SupplierCreditService $supplierCreditService) {}

    public function index(SupplierCreditIndexRequest $request): JsonResponse
    {
        $list = $this->supplierCreditService->list(SupplierCreditFilterDTO::from($request));

        return ApiResponse::success(data: [
            'summary' => $list['summary'],
            'clients' => SupplierCreditClientResource::collection($list['clients']),
        ]);
    }

    public function review(Request $request, int $id): JsonResponse
    {
        $event = $this->supplierCreditService->review($id, (int) $request->user()->id);

        return $event === null ? ApiResponse::notFound(message: self::NOT_FOUND) : ApiResponse::created(data: new ClientEventResource($event));
    }
}

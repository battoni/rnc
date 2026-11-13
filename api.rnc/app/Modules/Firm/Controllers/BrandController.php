<?php

namespace App\Modules\Firm\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Firm\DTOs\UpdateBrandDTO;
use App\Modules\Firm\Models\FirmBrand;
use App\Modules\Firm\Requests\UpdateBrandRequest;
use App\Modules\Firm\Resources\BrandResource;
use App\Modules\Firm\Resources\MainBrandResource;
use App\Modules\Firm\Services\BrandService;
use Illuminate\Http\JsonResponse;

/**
 * The sub-brands console (rnc-3 R5): `brands.manage`, the operator's alone (D3). The main brand's
 * row is the firm's own look, read-only here. Another firm's brand is a 404, never a 403.
 */
class BrandController extends Controller
{
    private const NOT_FOUND = 'brands.notFound';

    public function __construct(private BrandService $brandService) {}

    public function index(): JsonResponse
    {
        $list = $this->brandService->list();

        return ApiResponse::success(data: [
            'main' => new MainBrandResource($list['main']),
            'brands' => BrandResource::collection($list['brands']),
        ]);
    }

    public function update(UpdateBrandRequest $request, int $id): JsonResponse
    {
        return $this->answer($this->brandService->update($id, UpdateBrandDTO::from($request)));
    }

    public function activate(int $id): JsonResponse
    {
        return $this->answer($this->brandService->setActive($id, true));
    }

    public function deactivate(int $id): JsonResponse
    {
        return $this->answer($this->brandService->setActive($id, false));
    }

    /** @param  array{brand: FirmBrand, lead_count: int}|null  $brand */
    private function answer(?array $brand): JsonResponse
    {
        return ! $brand ? ApiResponse::notFound(message: self::NOT_FOUND) : ApiResponse::success(data: new BrandResource($brand));
    }
}

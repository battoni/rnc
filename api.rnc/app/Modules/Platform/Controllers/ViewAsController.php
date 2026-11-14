<?php

namespace App\Modules\Platform\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Platform\Enums\ViewAsEndReason;
use App\Modules\Platform\Resources\ViewAsSessionResource;
use App\Modules\Platform\Services\ViewAsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Acessar como suporte" (rnc-4 R26, D20): `platform.firms` + the firm-less operator. Start on the
 * firm the route names (201 the open session; 404 none, 409 already viewing, 422 the platform firm),
 * end with "Sair" (200 the closed session, or null when none was active), and the audit log.
 */
class ViewAsController extends Controller
{
    private const NOT_FOUND = 'firms.notFound';

    public function __construct(private ViewAsService $viewAsService) {}

    public function store(Request $request, int $id): JsonResponse
    {
        /** @var User $operator the route's middleware let only the operator through */
        $operator = $request->user();
        $session = $this->viewAsService->start($request, $operator, $id);

        return ! $session ? ApiResponse::notFound(message: self::NOT_FOUND) : ApiResponse::created(data: new ViewAsSessionResource($session));
    }

    public function destroy(Request $request): JsonResponse
    {
        $session = $this->viewAsService->end($request, ViewAsEndReason::Leave);

        return ApiResponse::success(data: $session ? new ViewAsSessionResource($session) : null);
    }

    public function index(): JsonResponse
    {
        return ApiResponse::paginated($this->viewAsService->log(), ViewAsSessionResource::class);
    }
}

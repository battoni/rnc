<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Modules\Auth\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserPermission
{
    public function __construct(
        private PermissionService $permissionService
    ) {}

    /**
     * `permission:a` requires `a`; `permission:a,b` requires ANY of them. The list form exists
     * for a read that serves two screens — the roles list feeds both the Roles screen and the
     * user form, and neither audience should need the other's key to open its own screen.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::unauthorized(message: 'Unauthenticated');
        }

        $granted = $this->permissionService->forUser($user);
        $hasAnyPermission = array_intersect($permissions, $granted) !== [];

        if (! $hasAnyPermission) {
            return ApiResponse::forbidden(message: 'You do not have permission to perform this action');
        }

        return $next($request);
    }
}

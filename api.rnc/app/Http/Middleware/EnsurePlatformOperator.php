<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Services\RoleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The platform console's routes answer the platform operator only (rnc-4 R24; alias
 * `platform.operator`): the `operador` role AND no firm. It sits behind `permission:platform.*`, so
 * a key that leaks onto a firm account — a per-user override, a role edited by hand in the
 * database — still opens nothing: a firm's account is a tenant, never the platform. 403
 * `platform.operatorOnly`.
 */
class EnsurePlatformOperator
{
    public const ALIAS = 'platform.operator';

    public const OPERATOR_ONLY = 'platform.operatorOnly';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        return $user instanceof User && self::isPlatformOperator($user)
            ? $next($request)
            : ApiResponse::forbidden(message: self::OPERATOR_ONLY);
    }

    public static function isPlatformOperator(User $user): bool
    {
        $role = $user->role;

        return $user->firm_id === null && $role instanceof Role && $role->slug === RoleService::ADMINISTRATOR_SLUG;
    }
}

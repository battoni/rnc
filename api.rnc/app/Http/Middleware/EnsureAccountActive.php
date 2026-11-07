<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Modules\Auth\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out an account that was deactivated while its session was still alive.
 *
 * Login already refuses an INACTIVE account, but a session opened before the change kept
 * working until it expired — deactivating someone did not actually take their access away.
 *
 * It answers 401 and ends the session, not 403. A 403 means "signed in, not allowed", and the
 * SPA keeps its session on a 403 by design; the person would be left inside an app that refuses
 * everything. A 401 is the SPA's signal to clear the session and go to the sign-in screen, where
 * login then refuses them with the same message as any other failed attempt.
 */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $isDeactivated = $request->user()?->status === UserStatus::INACTIVE;

        if (! $isDeactivated) {
            return $next($request);
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return ApiResponse::unauthorized(message: 'auth.accountInactive');
    }
}

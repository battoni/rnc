<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * `GET api/health` (rnc-4 F7, R35): what the load balancer and the uptime check ask. One `select 1`
 * on the default connection — 200 `{status: ok, db: ok}`, else 503 `{status: down, db: down}`. The
 * route runs without the session and the firm lookup (`routes/api.local.php`): both read the
 * database, and a probe must report it down instead of failing inside them with a 500. It answers no
 * firm and no person, so it is safe on any host.
 */
class HealthController extends Controller
{
    private const UP = 'ok';

    private const DOWN = 'down';

    private const SERVICE_UNAVAILABLE = 503;

    public function __invoke(): JsonResponse
    {
        try {
            DB::select('select 1');
        } catch (Throwable) {
            return ApiResponse::success(data: ['status' => self::DOWN, 'db' => self::DOWN], status: self::SERVICE_UNAVAILABLE);
        }

        return ApiResponse::success(data: ['status' => self::UP, 'db' => self::UP]);
    }
}

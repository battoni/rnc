<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ApiResponse
{
    /**
     * Wrap a paginator as a resourced payload. The Laravel paginator JSON
     * (current_page / data / last_page / per_page / total …) rides inside the
     * usual `data` envelope — the frontend `PaginateResult<T>` type mirrors it.
     */
    public static function paginated(LengthAwarePaginator $paginator, string $resourceClass, $status = 200): JsonResponse
    {
        return self::success(
            data: $paginator->through(fn ($item) => new $resourceClass($item)),
            status: $status
        );
    }

    /**
     * Same, for a collection already built and ordered in PHP, where the ordering cannot be
     * expressed in SQL: slice it into a paginator by hand.
     *
     * `$page` is floored here as well as at the request edge — the paginator normalises an
     * out-of-range page to 1 for reporting, so an unclamped slice would answer "page 1" with
     * the rows of a page that does not exist.
     */
    public static function paginatedCollection(Collection $items, int $page, int $perPage, string $resourceClass, $status = 200): JsonResponse
    {
        $currentPage = max(1, $page);

        $paginator = new LengthAwarePaginator(
            $items->forPage($currentPage, $perPage)->values(),
            $items->count(),
            $perPage,
            $currentPage
        );

        return self::paginated($paginator, $resourceClass, $status);
    }

    public static function success($data = null, $extraData = null, $status = 200, $cookie = null)
    {
        $responseData = [];

        if ($data !== null) {
            $responseData = ['data' => $data];
        }

        if ($extraData) {
            $responseData = array_merge($responseData, $extraData);
        }

        $response = response()->json(
            data: $responseData,
            status: $status,
            options: JSON_PRETTY_PRINT
        );

        if ($cookie) {
            $response = $response->withCookie(cookie: $cookie);
        }

        return $response;
    }

    public static function created($data = null, $extraData = null)
    {
        return self::success(data: $data, extraData: $extraData, status: 201);
    }

    public static function error($message, $extraData = null, $status = 400)
    {
        $responseData = [
            'data' => null,
            'error' => $message,
            'status_code' => $status,
        ];

        if ($extraData) {
            $responseData = array_merge($responseData, $extraData);
        }

        return response()->json(
            data: $responseData,
            status: $status,
            options: JSON_PRETTY_PRINT
        );
    }

    public static function badRequest($message, $extraData = null)
    {
        return self::error(message: $message, status: 400, extraData: $extraData);
    }

    public static function unauthorized($message, $extraData = null)
    {
        return self::error(message: $message, status: 401, extraData: $extraData);
    }

    public static function forbidden($message, $extraData = null)
    {
        return self::error(message: $message, status: 403, extraData: $extraData);
    }

    public static function notFound($message, $extraData = null)
    {
        return self::error(message: $message, status: 404, extraData: $extraData);
    }

    public static function conflict($message, $extraData = null)
    {
        return self::error(message: $message, status: 409, extraData: $extraData);
    }

    public static function internalError($message, $extraData = null)
    {
        return self::error(message: $message, status: 500, extraData: $extraData);
    }

    public static function serviceUnavailable($message, $extraData = null)
    {
        return self::error(message: $message, status: 503, extraData: $extraData);
    }
}

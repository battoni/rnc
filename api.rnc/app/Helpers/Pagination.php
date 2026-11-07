<?php

namespace App\Helpers;

use Illuminate\Http\Request;

class Pagination
{
    public const DEFAULT_PER_PAGE = 15;

    public const MAX_PER_PAGE = 100;

    /**
     * Read `per_page` off the request and clamp it to a sane window. Without the
     * ceiling any authenticated caller could ask for the whole table in one query;
     * without the floor a `per_page=0` would fall through to the model default and
     * silently change page size.
     */
    public static function perPage(Request $request): int
    {
        $perPage = $request->integer('per_page', self::DEFAULT_PER_PAGE);

        return max(1, min($perPage, self::MAX_PER_PAGE));
    }

    /**
     * Read `page` off the request, floored at the first page. Only needed where a
     * collection is sliced by hand — Eloquent's paginator resolves and clamps `page`
     * on its own.
     */
    public static function page(Request $request): int
    {
        return max(1, $request->integer('page', 1));
    }
}

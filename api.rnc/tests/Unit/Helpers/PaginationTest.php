<?php

use App\Helpers\Pagination;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(TestCase::class);

function paginationRequest(array $query): Request
{
    return Request::create('/api/auth/users', 'GET', $query);
}

it('per_page falls back to the default when absent', function () {
    expect(Pagination::perPage(paginationRequest([])))->toBe(Pagination::DEFAULT_PER_PAGE);
});

it('per_page passes a value inside the window through', function () {
    expect(Pagination::perPage(paginationRequest(['per_page' => 25])))->toBe(25);
});

it('per_page caps an oversized request at the maximum', function () {
    expect(Pagination::perPage(paginationRequest(['per_page' => 999999])))->toBe(Pagination::MAX_PER_PAGE);
});

it('per_page floors zero and negative values at one', function () {
    expect(Pagination::perPage(paginationRequest(['per_page' => 0])))->toBe(1)
        ->and(Pagination::perPage(paginationRequest(['per_page' => -5])))->toBe(1);
});

it('page defaults to the first page and floors anything below it', function () {
    expect(Pagination::page(paginationRequest([])))->toBe(1)
        ->and(Pagination::page(paginationRequest(['page' => 0])))->toBe(1)
        ->and(Pagination::page(paginationRequest(['page' => -3])))->toBe(1)
        ->and(Pagination::page(paginationRequest(['page' => 4])))->toBe(4);
});

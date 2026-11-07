<?php

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

uses(TestCase::class);

it('success returns 200 with data wrapped in data key', function () {
    $response = ApiResponse::success(['id' => 1]);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['data'])->toBe(['id' => 1]);
});

it('success returns 200 with no data key when null is passed', function () {
    $response = ApiResponse::success();

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->not->toHaveKey('data');
});

it('success merges extra data alongside the data key', function () {
    $response = ApiResponse::success(['id' => 1], ['meta' => 'extra']);

    $body = $response->getData(true);
    expect($body['data'])->toBe(['id' => 1])
        ->and($body['meta'])->toBe('extra');
});

it('created returns 201 with data', function () {
    $response = ApiResponse::created(['id' => 99]);

    expect($response->getStatusCode())->toBe(201)
        ->and($response->getData(true)['data'])->toBe(['id' => 99]);
});

it('error returns the given status code with error and status_code keys', function () {
    $response = ApiResponse::error('Something failed', null, 422);

    $body = $response->getData(true);
    expect($response->getStatusCode())->toBe(422)
        ->and($body['error'])->toBe('Something failed')
        ->and($body['data'])->toBeNull()
        ->and($body['status_code'])->toBe(422);
});

it('error merges extra data', function () {
    $response = ApiResponse::error('Oops', ['field' => 'username'], 400);

    expect($response->getData(true)['field'])->toBe('username');
});

it('badRequest returns 400', function () {
    $response = ApiResponse::badRequest('Bad input');

    expect($response->getStatusCode())->toBe(400)
        ->and($response->getData(true)['error'])->toBe('Bad input');
});

it('unauthorized returns 401', function () {
    $response = ApiResponse::unauthorized('Unauthenticated');

    expect($response->getStatusCode())->toBe(401);
});

it('forbidden returns 403', function () {
    $response = ApiResponse::forbidden('Forbidden');

    expect($response->getStatusCode())->toBe(403);
});

it('notFound returns 404', function () {
    $response = ApiResponse::notFound('Not found');

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getData(true)['error'])->toBe('Not found');
});

it('conflict returns 409', function () {
    $response = ApiResponse::conflict('Already exists');

    expect($response->getStatusCode())->toBe(409);
});

it('internalError returns 500', function () {
    $response = ApiResponse::internalError('Server error');

    expect($response->getStatusCode())->toBe(500);
});

it('paginated keeps the envelope and nests the paginator inside data', function () {
    $paginator = new LengthAwarePaginator(
        collect([['id' => 1], ['id' => 2]]),
        total: 5,
        perPage: 2,
        currentPage: 1,
    );

    $body = ApiResponse::paginated($paginator, JsonResource::class)->getData(true);

    expect($body['data']['data'])->toBe([['id' => 1], ['id' => 2]])
        ->and($body['data']['total'])->toBe(5)
        ->and($body['data']['per_page'])->toBe(2)
        ->and($body['data']['last_page'])->toBe(3);
});

it('paginatedCollection slices the requested page', function () {
    $items = collect(range(1, 5))->map(fn (int $id) => ['id' => $id]);

    $body = ApiResponse::paginatedCollection($items, 2, 2, JsonResource::class)->getData(true);

    expect(array_column($body['data']['data'], 'id'))->toBe([3, 4])
        ->and($body['data']['current_page'])->toBe(2)
        ->and($body['data']['total'])->toBe(5);
});

it('paginatedCollection floors a page below one instead of answering an empty slice', function () {
    $items = collect(range(1, 3))->map(fn (int $id) => ['id' => $id]);

    $body = ApiResponse::paginatedCollection($items, 0, 2, JsonResource::class)->getData(true);

    expect(array_column($body['data']['data'], 'id'))->toBe([1, 2])
        ->and($body['data']['current_page'])->toBe(1);
});

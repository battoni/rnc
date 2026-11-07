import { test } from 'node:test';
import assert from 'node:assert/strict';
import { parseEndpoints, parseMiddlewareAliases, resolveMiddlewareTail } from './extract-endpoints.mjs';
import { stripPhpComments } from './lib/strip-php-comments.mjs';

test('parseMiddlewareAliases: resolves string-only middleware arrays, skips entity maps', () => {
  const src = `
$rolesRead = ['auth', 'permission:permissions.read,users.create'];
$tipsManage = ["auth", "permission:tips.manage"];
$entities = [
    'machines' => MachineController::class,
];
`;
  const aliases = parseMiddlewareAliases(src);
  assert.deepEqual(aliases.rolesRead, ['auth', 'permission:permissions.read,users.create']);
  assert.deepEqual(aliases.tipsManage, ['auth', 'permission:tips.manage']);
  assert.equal(aliases.entities, undefined);
});

test('resolveMiddlewareTail: expands $var into quoted middleware tokens, leaves unknown names alone', () => {
  const aliases = { rolesRead: ['auth', 'permission:permissions.read'] };
  assert.equal(resolveMiddlewareTail('->middleware($rolesRead)', aliases), "->middleware(['auth', 'permission:permissions.read'])");
  assert.equal(resolveMiddlewareTail('->middleware($unknown)', aliases), '->middleware($unknown)');
});

test('parseEndpoints: a route inside a block or line comment is not an endpoint', () => {
  const src = `<?php
use App\\Modules\\Shop\\Controllers\\OrderController;
/*
 * Example:
 *   use App\\Modules\\Catalog\\Controllers\\ProductCategoryController;
 *   Route::get('catalog/product-categories', [ProductCategoryController::class, 'index'])
 *       ->middleware(['auth', 'permission:catalog.read']);
 */
// Route::delete('orders/{id}', [OrderController::class, 'destroy']);
Route::get('orders', [OrderController::class, 'index'])->middleware('auth'); // Route::post('ghost', [OrderController::class, 'store']);
Route::get('links', [OrderController::class, 'links'])->middleware('permission:orders.read'); # Route::put('hash', [OrderController::class, 'update']);
`;
  const endpoints = parseEndpoints(src);
  assert.deepEqual(
    endpoints.map((endpoint) => `${endpoint.verb} ${endpoint.path} ${endpoint.module}`),
    ['GET /orders shop', 'GET /links shop'],
  );
});

test('parseEndpoints: a group\'s permission reaches every route inside it, joined to the route\'s own', () => {
  const src = `<?php
use App\\Modules\\Idea\\Controllers\\IdeaController;
use App\\Modules\\Idea\\Controllers\\TagController;
Route::middleware(['auth', 'permission:ideas.read'])->group(function (): void {
    Route::get('ideas', [IdeaController::class, 'index']);
    Route::post('ideas', [IdeaController::class, 'store'])->middleware(['permission:ideas.create', 'throttle:ideas']);
    Route::prefix('admin')->middleware('permission:ideas.manage,news.manage')->group(function (): void {
        Route::patch('ideas/{id}/hide', [IdeaController::class, 'hide']);
    });
    Route::apiResource('tags', TagController::class)->only(['index']);
});
Route::get('open', [IdeaController::class, 'open']);
`;
  assert.deepEqual(
    parseEndpoints(src).map((endpoint) => `${endpoint.verb} ${endpoint.path} ${endpoint.auth ? 'auth' : 'public'} ${endpoint.permission}`),
    [
      'GET /ideas auth ideas.read',
      'POST /ideas auth ideas.read + ideas.create',
      'PATCH /admin/ideas/{id}/hide auth ideas.read + ideas.manage,news.manage',
      'GET /open public null',
      'GET /tags auth ideas.read',
    ],
  );
});

test('stripPhpComments: keeps a // or # that sits inside a string', () => {
  const src = "$url = 'https://example.test/#top'; // gone\n#[Attribute]\n";
  assert.equal(stripPhpComments(src), "$url = 'https://example.test/#top'; \n#[Attribute]\n");
});

// The two cases above test the helpers alone, because the template has no routes file of its own.
// The end-to-end case — `extractEndpoints()` reporting `auth: true` and every `permission:` key for
// a route guarded through a variable — needs real routes; docs.rnc's copy of this file shows it.

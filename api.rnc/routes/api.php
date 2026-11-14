<?php

use App\Http\Controllers\QaLoginAsController;
use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Auth\Controllers\LoginCodeController;
use App\Modules\Auth\Controllers\MeController;
use App\Modules\Auth\Controllers\RegisterController;
use App\Modules\Auth\Controllers\RoleController;
use App\Modules\Auth\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// AUTH
Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('auth/logout', [AuthController::class, 'logout'])->middleware('auth');
Route::get('auth/me', [AuthController::class, 'me'])->middleware('auth');
// Self-service: an account's own name. Nothing that somebody else decides about it — those stay on
// `auth/users/{id}` behind `users.update`.
Route::patch('auth/me', [MeController::class, 'update'])->middleware('auth');
// Same audience as the roles read: the Roles screen, and the user form that grants overrides.
Route::get('auth/permission-groups', [AuthController::class, 'permissionGroups'])->middleware(['auth', 'permission:permissions.read,users.create,users.update']);
// Public on purpose: the forgot-password screen, which nobody signed in reaches, names who can reset
// a password. Throttled per address so it is not a free list of the product's privileged people.
Route::get('auth/support-names', [AuthController::class, 'supportNames'])->middleware('throttle:support-names');

// SIGN-IN BY PHONE CODE
// Public, and throttled per address and per phone (`AppServiceProvider`). `code` asks for a code
// and answers the same whether or not the phone has an account; `verify` spends it.
Route::post('auth/phone/code', [LoginCodeController::class, 'requestCode'])->middleware('throttle:login-code');
Route::post('auth/phone/verify', [LoginCodeController::class, 'verify'])->middleware('throttle:login-code-verify');

// SELF-SERVICE REGISTRATION
// Throttled because none of the three has an authenticated caller. `register` is the write and
// `email/code` re-sends — the one an enumeration script would lean on hardest — both by IP.
// `verify` is the guess, so it is counted by IP and by e-mail, like the login door.
// Closed unless `registration.enabled` (rnc-4 R7, D21): `registration.open` answers 404 first.
Route::post('auth/register', [RegisterController::class, 'register'])->middleware(['registration.open', 'throttle:registration']);
Route::post('auth/email/code', [RegisterController::class, 'requestCode'])->middleware(['registration.open', 'throttle:registration']);
Route::post('auth/email/verify', [RegisterController::class, 'verify'])->middleware(['registration.open', 'throttle:registration-verify']);

// ROLES
// A permission per verb, never one for the resource. These routes used to ask only for a session,
// so any signed-in account could create a role, grant it every permission and delete the
// administrator's — and the list was public. Reading serves two screens: the Roles screen, and
// the user form that picks a role, so either audience's key opens it. Roles are global (one set for
// every firm), so a write is the platform operator's alone (`platform.operator`, rnc-4 verify PE1):
// a firm account holding `permissions.update` would otherwise edit every firm's roles.
$rolesRead = ['auth', 'permission:permissions.read,users.create,users.update'];
$rolesWrite = ['auth', 'permission:permissions.update', 'platform.operator'];

Route::get('auth/roles', [RoleController::class, 'index'])->middleware($rolesRead);
Route::get('auth/roles/{id}', [RoleController::class, 'show'])->middleware($rolesRead);
Route::post('auth/roles', [RoleController::class, 'store'])->middleware($rolesWrite);
Route::patch('auth/roles/{id}', [RoleController::class, 'update'])->middleware($rolesWrite);
Route::patch('auth/roles/{id}/permissions', [RoleController::class, 'updatePermissions'])->middleware($rolesWrite);
Route::delete('auth/roles/{id}', [RoleController::class, 'destroy'])->middleware($rolesWrite);

// USERS (permission-protected)
// Only the user form asks, so only its audience may: a member asking "does battoni exist" is
// enumerating accounts.
Route::get('auth/users/check-username', [UserController::class, 'checkUsername'])->middleware(['auth', 'permission:users.create,users.update']);
Route::get('auth/users', [UserController::class, 'index'])->middleware(['auth', 'permission:users.read']);
Route::get('auth/users/{id}', [UserController::class, 'show'])->middleware(['auth', 'permission:users.read']);
Route::post('auth/users', [UserController::class, 'store'])->middleware(['auth', 'permission:users.create']);
Route::patch('auth/users/{id}', [UserController::class, 'update'])->middleware(['auth', 'permission:users.update']);
Route::patch('auth/users/{id}/archive', [UserController::class, 'archive'])->middleware(['auth', 'permission:users.archive']);
Route::delete('auth/users/{id}', [UserController::class, 'destroy'])->middleware(['auth', 'permission:users.delete']);

/*
 * QA login-as: signs in WITHOUT a password, for the Chrome driver (QaLoginAsController).
 *
 * In the api group, not web: this group starts the session without EncryptCookies, so a session
 * set by a web route is a cookie the API cannot read (boanerges, 2026-11-04: login-as answered 302
 * and `auth/me` still said Unauthenticated). Registered in local and testing (where the tests prove
 * the refusal); the controller itself refuses anything but local. Never in production.
 */
if (app()->environment('local', 'testing')) {
    Route::get('qa/login-as/{username}', QaLoginAsController::class);
}

// PROJECT-OWNED ROUTES (LOCAL) — registered in routes/api.local.php; the cortex sync never
// touches that file, so feature-module routes survive every rnc update.
if (file_exists(__DIR__.'/api.local.php')) {
    require __DIR__.'/api.local.php';
}

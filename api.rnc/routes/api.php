<?php

use App\Http\Controllers\QaLoginAsController;
use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Auth\Controllers\LoginCodeController;
use App\Modules\Auth\Controllers\MeController;
use App\Modules\Auth\Controllers\RegisterController;
use App\Modules\Auth\Controllers\RoleController;
use App\Modules\Auth\Controllers\UserController;
use App\Modules\Idea\Controllers\IdeaCommentController;
use App\Modules\Idea\Controllers\IdeaController;
use App\Modules\Idea\Controllers\IdeaModerationController;
use App\Modules\Idea\Controllers\IdeaVoteController;
use App\Modules\Idea\Controllers\NewsController;
use App\Modules\Tip\Controllers\TipController;
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
Route::post('auth/register', [RegisterController::class, 'register'])->middleware('throttle:registration');
Route::post('auth/email/code', [RegisterController::class, 'requestCode'])->middleware('throttle:registration');
Route::post('auth/email/verify', [RegisterController::class, 'verify'])->middleware('throttle:registration-verify');

// ROLES
// A permission per verb, never one for the resource. These routes used to ask only for a session,
// so any signed-in account could create a role, grant it every permission and delete the
// administrator's — and the list was public. Reading serves two screens: the Roles screen, and
// the user form that picks a role, so either audience's key opens it.
$rolesRead = ['auth', 'permission:permissions.read,users.create,users.update'];
$rolesWrite = ['auth', 'permission:permissions.update'];

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
 * IDEAS — the feedback board, and the only PUBLIC surface in this API.
 *
 * Every other module answers "what is MINE"; this one answers "what has everyone asked for".
 * That inverts where the danger is: scoping is not the protection here, moderation is, and the
 * three keys split the board's three different acts — reading it, writing to it, and running it.
 *
 * `ideas.read` gates the group because a person who cannot see the board has no business voting
 * on it or commenting in it either. `ideas.create` is separate so a noisy account can be muted
 * without being blinded. `ideas.manage` is the maintainer's: status, note, hide, merge, and the
 * changelog.
 *
 * The literal sub-paths (`{id}/comments`, `{id}/vote`, `{id}/status`, `comments/{id}`) are declared
 * BEFORE the bare `ideas/{id}` handlers — a bare `{id}` route declared first would swallow anything
 * shaped like it.
 */
Route::middleware(['auth', 'permission:ideas.read'])->group(function (): void {
    Route::get('ideas', [IdeaController::class, 'index']);

    /*
     * Throttled because this is the one endpoint in the product where one account writes text
     * that every other account then reads. A ceiling is not about abuse in the abstract: without
     * it, a single script fills the board and the board stops being readable for everyone else.
     */
    Route::post('ideas', [IdeaController::class, 'store'])->middleware(['permission:ideas.create', 'throttle:ideas']);

    Route::get('ideas/{id}/comments', [IdeaCommentController::class, 'index']);
    Route::post('ideas/{id}/comments', [IdeaCommentController::class, 'store'])->middleware('throttle:idea-comments');
    Route::delete('ideas/comments/{id}', [IdeaCommentController::class, 'destroy']);

    Route::post('ideas/{id}/vote', [IdeaVoteController::class, 'store']);
    Route::delete('ideas/{id}/vote', [IdeaVoteController::class, 'destroy']);

    Route::patch('ideas/{id}/status', [IdeaModerationController::class, 'updateStatus'])->middleware('permission:ideas.manage');
    Route::patch('ideas/{id}/hide', [IdeaModerationController::class, 'hide'])->middleware('permission:ideas.manage');
    Route::post('ideas/{id}/merge', [IdeaModerationController::class, 'merge'])->middleware('permission:ideas.manage');

    Route::get('ideas/{id}', [IdeaController::class, 'show']);
    Route::patch('ideas/{id}', [IdeaController::class, 'update']);
    Route::delete('ideas/{id}', [IdeaController::class, 'destroy']);

    /*
     * NOVIDADES — the product's changelog. Reading is part of using the product, so it rides on
     * `ideas.read` with the rest of the group; writing has its OWN key. It used to reuse
     * `ideas.manage`, on the argument that nobody would grant one without the other — no longer
     * true now that an entry is a cover, a standfirst and an article somebody sits down to write.
     */
    Route::get('news', [NewsController::class, 'index']);
    Route::get('news/unread-count', [NewsController::class, 'unreadCount']);
    Route::post('news/{id}/read', [NewsController::class, 'read']);
    /*
     * Applause is unlimited by design, so the throttle is the only thing standing between an
     * enthusiastic thumb and a script. It is generous enough that nobody clicking by hand will
     * ever meet it.
     */
    Route::post('news/{id}/reactions', [NewsController::class, 'react'])->middleware('throttle:60,1');
    Route::get('news/{id}/readers', [NewsController::class, 'readers'])->middleware('permission:news.manage');
    Route::post('news', [NewsController::class, 'store'])->middleware('permission:news.manage');
    Route::patch('news/{id}', [NewsController::class, 'update'])->middleware('permission:news.manage');
    Route::patch('news/{id}/publish', [NewsController::class, 'publish'])->middleware('permission:news.manage');
    Route::delete('news/{id}', [NewsController::class, 'destroy'])->middleware('permission:news.manage');
});

/*
 * TIPS — editable contextual help. Read = `tips.read` for the management view and the audit trail;
 * mutations = `tips.manage`.
 *
 * `tips/active` sits beside `tips/{id}`. Declaration order does not decide which one answers:
 * `{id}` only matches digits (`Route::patterns` in AppServiceProvider), so the literal is reached
 * wherever it is declared.
 */
$tipsRead = ['auth', 'permission:tips.read'];
$tipsManage = ['auth', 'permission:tips.manage'];

// The rendering payload is open to any authenticated user: a tip is help attached to a screen, and
// gating it would blank the icon for exactly the people it exists for.
Route::get('tips/active', [TipController::class, 'active'])->middleware('auth');
Route::get('tips', [TipController::class, 'index'])->middleware($tipsRead);
Route::post('tips', [TipController::class, 'store'])->middleware($tipsManage);
Route::get('tips/{id}/history', [TipController::class, 'history'])->middleware($tipsRead);
Route::post('tips/{id}/restore/{historyId}', [TipController::class, 'restore'])->middleware($tipsManage);
Route::patch('tips/{id}/unarchive', [TipController::class, 'unarchive'])->middleware($tipsManage);
Route::get('tips/{id}', [TipController::class, 'show'])->middleware($tipsRead);
Route::patch('tips/{id}', [TipController::class, 'update'])->middleware($tipsManage);
Route::delete('tips/{id}', [TipController::class, 'destroy'])->middleware($tipsManage);

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

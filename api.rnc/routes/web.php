<?php

use App\Http\Controllers\MailPreviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
 * Looking at the e-mails without sending them.
 *
 * LOCAL ONLY, and the guard is the point: these render with sample data and no session, so
 * in production they would be an unauthenticated page printing whatever the sample says.
 * `isLocal()` reads APP_ENV, which is `production` on every deployed environment.
 */
if (app()->isLocal()) {
    Route::get('/mail-preview', [MailPreviewController::class, 'index']);
    Route::get('/mail-preview/{message}', [MailPreviewController::class, 'show']);
}

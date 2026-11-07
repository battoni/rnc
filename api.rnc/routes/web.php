<?php

use App\Http\Controllers\MailPreviewController;
use App\Modules\Idea\Controllers\NewsShareController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
 * The share link for a News entry. PUBLIC on purpose, and deliberately short — it is typed into
 * a chat, read by a crawler, and clicked by a person, in that order.
 *
 * Only the preview is public: headline, standfirst and cover. The article needs a session, which
 * is why this hands the reader to the app rather than rendering the body here.
 */
Route::get('/n/{id}', [NewsShareController::class, 'show'])->whereNumber('id')->name('news.share');

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

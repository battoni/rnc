<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\Auth\Enums\UserStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Signs in as a QA user WITHOUT a password, then hands the browser to the app.
 *
 * LOCAL ONLY. The Chrome driver opens this instead of typing into the login form, where the
 * owner's 1Password pop-up breaks the extension, and each QA round starts here so it never runs
 * on a session left by the round before. The route is registered only in local and testing
 * (`routes/api.php`, which says why it sits in the api group), and this refuses anything but
 * APP_ENV=local even when it is reached, so a wrong registration still signs no one in.
 * `tests/Feature/QaLoginAsTest.php`.
 */
class QaLoginAsController extends Controller
{
    public function __invoke(Request $request, string $username): RedirectResponse
    {
        abort_unless(app()->isLocal(), 404);

        $user = User::query()->where('username', $username)->first();
        abort_if($user === null || $user->status === UserStatus::INACTIVE, 404);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->away(config('rnc.frontend_url').$this->path($request->query('to')));
    }

    /** Only a path inside the app: `//host`, `/\host` and a full URL all fall back to `/`. */
    private function path(mixed $to): string
    {
        return is_string($to) && preg_match('#^/(?![/\\\\])#', $to) === 1 ? $to : '/';
    }
}

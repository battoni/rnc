<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ResolveCurrentFirm;
use App\Models\User;
use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\FirmResolver;
use App\Modules\Firm\Services\MailBrand;
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
 *
 * It obeys the same firm rule as the password login (rnc-4 R4, plan AM8): the driver opens it on
 * the firm's own host (`http://franco.localhost:5173/api/qa/login-as/patricia`, through Vite's
 * proxy, which keeps the Host), and an account of another firm — or the operator anywhere but the
 * root — is the same 404 as an unknown one. A browser navigation sends no `X-Firm`, so the firm is
 * the header's when there is one, else the host's; it is decided here and not read from
 * `CurrentFirm`, which the middleware set from the session this door is about to replace.
 * `tests/Feature/Auth/LoginFirmTest.php`.
 */
class QaLoginAsController extends Controller
{
    public function __construct(private FirmResolver $firmResolver) {}

    public function __invoke(Request $request, string $username): RedirectResponse
    {
        abort_unless(app()->isLocal(), 404);

        $user = User::query()->where('username', $username)->first();
        abort_if($user === null || $user->status === UserStatus::INACTIVE, 404);
        $firm = $this->requestedFirm($request);
        abort_unless($this->firmResolver->belongsTo($user, $firm), 404);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->away($this->siteOf($request, $firm).$this->path($request->query('to')));
    }

    /** The firm the address names: the `X-Firm` header when sent, else the browser's host. */
    private function requestedFirm(Request $request): ?Firm
    {
        $slug = $request->header(ResolveCurrentFirm::HEADER);

        return $slug !== null
            ? $this->firmResolver->findTenantBySlug($slug)
            : $this->firmResolver->findFirmByHost($request->getHost());
    }

    /**
     * Where the browser lands: the root when the address was the root, else the firm's own site
     * (`MailBrand::siteUrlOf`, the builder the mail links use). Always sending the root took
     * alvorada's member off the host the session was opened for (QA 1.11). Built from config,
     * never echoed from the Host header.
     */
    private function siteOf(Request $request, ?Firm $firm): string
    {
        $root = (string) config('rnc.frontend_url');
        $namesFirm = $request->hasHeader(ResolveCurrentFirm::HEADER)
            || str_ends_with($request->getHost(), '.'.config('platform.root_domain'));

        return $firm !== null && $namesFirm ? MailBrand::siteUrlOf($firm) : $root;
    }

    /** Only a path inside the app: `//host`, `/\host` and a full URL all fall back to `/`. */
    private function path(mixed $to): string
    {
        return is_string($to) && preg_match('#^/(?![/\\\\])#', $to) === 1 ? $to : '/';
    }
}

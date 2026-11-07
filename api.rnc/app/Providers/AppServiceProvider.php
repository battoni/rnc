<?php

namespace App\Providers;

use App\Helpers\Brazil\AddressColumns;
use App\Helpers\Brazil\PhoneNumber;
use App\Modules\Auth\Contracts\LoginCodeSender;
use App\Modules\Auth\Senders\LogLoginCodeSender;
use App\Modules\Firm\Services\CurrentBrand;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Partner\Services\CurrentPartner;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Name => class for `auth.login_code.sender`. A product adds its own driver here; an unknown
     * name fails when the sender is first needed rather than falling back to one that delivers
     * nothing.
     */
    private const LOGIN_CODE_SENDERS = [
        'log' => LogLoginCodeSender::class,
    ];

    /*
     * Asking for a code costs a delivery, so it is tight; checking one is looser because a
     * person mistypes, and each code already burns itself on the fifth wrong guess.
     */
    private const LOGIN_CODE_REQUESTS_PER_MINUTE = 5;

    private const LOGIN_CODE_VERIFICATIONS_PER_MINUTE = 10;

    /** Five a minute: above a person mistyping their own password, below anything automated. */
    private const LOGIN_ATTEMPTS_PER_MINUTE = 5;

    /** Registration writes, by address: generous for a person, useless for a script. */
    private const REGISTRATION_ATTEMPTS_PER_MINUTE = 10;

    /** A person re-sends their own code once or twice; three a minute leaves room and stops a flood. */
    private const REGISTRATION_ATTEMPTS_PER_EMAIL_PER_MINUTE = 3;

    /*
     * Checking an e-mail code, by address AND by e-mail. The address budget is the registration
     * one; the e-mail budget is what a distributed guess on one address meets. Each code also burns
     * itself on the fifth wrong guess (`EmailVerificationService::MAX_ATTEMPTS`).
     */
    private const REGISTRATION_VERIFICATIONS_PER_MINUTE = 10;

    /*
     * The forgot-password screen reads the support names on load and is public. A person opens it
     * a handful of times; a script listing the product's privileged people should not.
     */
    private const SUPPORT_NAMES_REQUESTS_PER_MINUTE = 10;

    /*
     * Route parameters that only ever carry an integer id. Constrained app-wide so a literal
     * segment (`leads/options`) is reached whatever the declaration order — `routes/api.local.php`
     * is required AFTER the core routes, and without this `leads/{id}` swallows the word and
     * `show(int $id)` answers a 500. A new numeric parameter name goes here.
     */
    private const NUMERIC_ROUTE_PARAMETERS = ['id'];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LoginCodeSender::class, function ($app) {
            $driver = (string) config('auth.login_code.sender');
            $senderClass = self::LOGIN_CODE_SENDERS[$driver] ?? null;

            if (! $senderClass) {
                throw new InvalidArgumentException("Unknown login code sender [{$driver}].");
            }

            return $app->make($senderClass);
        });

        // Scoped, not a singleton: the firm of one request must never be the next one's (AM6).
        $this->app->scoped(CurrentFirm::class);
        // The portal's partner, the same way (rnc-2 D2): set per request by `ResolveCurrentPartner`.
        $this->app->scoped(CurrentPartner::class);
        // The public site's sub-brand, the same way (rnc-3 R2): set per request by `ResolveCurrentBrand`.
        $this->app->scoped(CurrentBrand::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * The brand, handed to every mail view.
         *
         * An e-mail carries no stylesheet, so the chrome needs these as literals — and no
         * Mailable should have to remember to pass them. A composer means a new message
         * extends the layout and is done.
         */
        View::composer('mail.*', function ($view) {
            $view->with([
                'accent' => config('rnc.brand.accent'),
                'brandLogo' => config('rnc.brand.logo') ? public_path(config('rnc.brand.logo')) : null,
                'brandName' => config('rnc.brand.name'),
                'frontendUrl' => config('rnc.frontend_url'),
                'ink' => config('rnc.brand.ink'),
            ]);
        });

        Route::patterns(array_fill_keys(self::NUMERIC_ROUTE_PARAMETERS, '[0-9]+'));

        $this->configureRateLimiting();

        // `$table->addressColumns()` / `localityColumns()` — Brazil's address block (Helpers/Brazil).
        AddressColumns::register();
    }

    /**
     * Named rate limiters, referenced by `throttle:<name>` on a route.
     *
     * A route naming a limiter that was never registered does not degrade — Laravel throws. So
     * a module whose routes carry `throttle:` brings its limiter with it, or it brings a 500.
     */
    private function configureRateLimiting(): void
    {
        /*
         * The login door, counted twice on purpose.
         *
         * By ADDRESS, which stops one machine working through a list of accounts; and by
         * USERNAME, which stops a distributed attempt on one account — the second is the one an
         * address limit alone never sees, because each request comes from somewhere new. Five a
         * minute is far above anyone typing their own password wrong and far below anything
         * automated.
         *
         * The username is lowercased before it becomes a key, or `Alice` and `alice` would be
         * two separate budgets on the same account.
         */
        RateLimiter::for('login', function (Request $request) {
            $username = (string) $request->input('username', '');

            return [
                Limit::perMinute(self::LOGIN_ATTEMPTS_PER_MINUTE)->by('login|ip|'.$request->ip()),
                Limit::perMinute(self::LOGIN_ATTEMPTS_PER_MINUTE)->by('login|user|'.mb_strtolower($username)),
            ];
        });

        /*
         * Keyed by address AND by phone, like `login`: the phone key stops a distributed attempt
         * on one number. The phone is normalised here too — the limiter runs before the
         * FormRequest does, and `(11) 99999-8888` must not be a separate budget from its digits.
         */
        RateLimiter::for('login-code', function (Request $request) {
            $phone = (string) PhoneNumber::normalize((string) $request->input('phone', ''));

            return [
                Limit::perMinute(self::LOGIN_CODE_REQUESTS_PER_MINUTE)->by('login-code|ip|'.$request->ip()),
                Limit::perMinute(self::LOGIN_CODE_REQUESTS_PER_MINUTE)->by('login-code|phone|'.$phone),
            ];
        });

        RateLimiter::for('login-code-verify', function (Request $request) {
            $phone = (string) PhoneNumber::normalize((string) $request->input('phone', ''));

            return [
                Limit::perMinute(self::LOGIN_CODE_VERIFICATIONS_PER_MINUTE)->by('login-code-verify|ip|'.$request->ip()),
                Limit::perMinute(self::LOGIN_CODE_VERIFICATIONS_PER_MINUTE)->by('login-code-verify|phone|'.$phone),
            ];
        });

        /*
         * Registration, by IP. Ten a minute is generous for a person and useless for a
         * script, and the pending row expires in fifteen minutes.
         */
        RateLimiter::for('registration', function (Request $request) {
            $email = mb_strtolower(trim((string) $request->input('email', '')));

            /*
             * By address AND by e-mail, like `registration-verify`. By address alone, somebody with
             * a few machines could keep re-sending codes to one inbox — each resend also replaces
             * the code its owner is about to type — without any single address meeting the
             * ceiling. The e-mail key is the one that sees that.
             */
            return [
                Limit::perMinute(self::REGISTRATION_ATTEMPTS_PER_MINUTE)->by('registration|ip|'.$request->ip()),
                Limit::perMinute(self::REGISTRATION_ATTEMPTS_PER_EMAIL_PER_MINUTE)->by('registration|email|'.$email),
            ];
        });

        /*
         * The guess itself, counted twice like `login`: the e-mail key is the one a distributed
         * attempt on one address cannot spread across machines. Lowercased, or `Ana@` and `ana@`
         * would be two budgets for one person.
         */
        RateLimiter::for('registration-verify', function (Request $request) {
            $email = mb_strtolower(trim((string) $request->input('email', '')));

            return [
                Limit::perMinute(self::REGISTRATION_VERIFICATIONS_PER_MINUTE)->by('registration-verify|ip|'.$request->ip()),
                Limit::perMinute(self::REGISTRATION_VERIFICATIONS_PER_MINUTE)->by('registration-verify|email|'.$email),
            ];
        });

        RateLimiter::for('support-names', function (Request $request) {
            return Limit::perMinute(self::SUPPORT_NAMES_REQUESTS_PER_MINUTE)->by('support-names|ip|'.$request->ip());
        });
    }
}

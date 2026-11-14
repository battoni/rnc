<?php

namespace App\Modules\Platform\Listeners;

use App\Models\User;
use App\Modules\Platform\Services\ViewAsService;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

/**
 * Every sign-in — password, phone code, the QA login-as — closes the view-as sessions this person
 * left open as `expirou` (rnc-4 D20): a session that ended without Sair or logout is closed here, and
 * the open row of another browser stops being honoured there too. Registered in `AppServiceProvider`.
 */
class CloseViewAsOnSignIn
{
    public function __construct(private ViewAsService $viewAsService, private Request $request) {}

    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            $this->viewAsService->closeLeftOpen($event->user, $this->request);
        }
    }
}

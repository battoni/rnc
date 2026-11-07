<?php

namespace App\Modules\Auth\Senders;

use App\Modules\Auth\Contracts\LoginCodeSender;
use Illuminate\Support\Facades\Log;

/**
 * The default driver: writes that a code was issued and delivers nothing.
 *
 * The code itself is never written — a log is read by more people than a phone is. Locally the
 * code reaches the screen through `auth.login_code.expose`. In production this driver REFUSES,
 * so a deploy that forgot to bind a real sender fails at the first request instead of issuing
 * codes nobody receives.
 */
class LogLoginCodeSender implements LoginCodeSender
{
    private const VISIBLE_PHONE_DIGITS = 4;

    public function send(string $phone, string $code): bool
    {
        if (app()->isProduction()) {
            Log::error('auth.login_code.log_sender_in_production');

            return false;
        }

        Log::info('auth.login_code.issued', ['phone_suffix' => substr($phone, -self::VISIBLE_PHONE_DIGITS)]);

        return true;
    }
}

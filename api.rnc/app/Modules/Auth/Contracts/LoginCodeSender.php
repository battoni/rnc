<?php

namespace App\Modules\Auth\Contracts;

/**
 * How a sign-in code reaches the person. The bootstrap ships only the `log` driver; a product
 * binds its own (SMS, WhatsApp, e-mail) under a name in `AppServiceProvider::LOGIN_CODE_SENDERS`
 * and selects it with `LOGIN_CODE_SENDER`.
 */
interface LoginCodeSender
{
    /**
     * Deliver the code. Returns false when it was not delivered, and must not throw for a
     * provider failure — the service turns false into a 503 the screen can explain.
     */
    public function send(string $phone, string $code): bool;
}

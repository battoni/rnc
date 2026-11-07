<?php

use App\Modules\Auth\Enums\UserStatus;

return [

    /*
    |--------------------------------------------------------------------------
    | Self-service registration
    |--------------------------------------------------------------------------
    |
    | Products in this family do not agree on how someone gets an account, so the
    | flow is switched here rather than forked in code.
    |
    */

    // OFF and `POST /auth/register` creates the user straight away; ON and it parks a
    // pending registration and mails a code that `POST /auth/email/verify` exchanges.
    'verify_email' => env('REGISTRATION_VERIFY_EMAIL', true),

    'code_ttl_minutes' => (int) env('REGISTRATION_CODE_TTL_MINUTES', 15),

    /*
    | What a self-registered account is worth on arrival. `active` lets the person in as
    | soon as the e-mail is proved; `inactive` parks them for a human to approve, which is
    | what a product with paid or vetted accounts wants.
    */
    'new_user_status' => env('REGISTRATION_NEW_USER_STATUS', UserStatus::ACTIVE->value),

    /*
    | Profile => the role that profile gets. **A SINGLE entry means the screen asks
    | nothing** — it is not a choice, so it is not a question, and the API fills it in.
    | Add entries and the sign-up screen starts asking.
    |
    | The list is also the allow-list: the API validates against these keys, so a
    | hand-made request cannot ask for a role the product never offered. The screen not
    | showing a choice is not the same as the endpoint refusing one.
    */
    'profile_types' => [
        'user' => 'user',
    ],

];

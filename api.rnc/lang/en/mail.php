<?php

return [
    'common' => [
        'code_label' => 'Your code',
        // Says why this arrived. An e-mail that does not explain itself reads as spam even
        // when the person asked for it.
        'footer_reason' => 'You received this because someone used this address to create an account.',
    ],

    'verification' => [
        'subject' => 'Your verification code',
        'preheader' => 'The code is valid for :minutes minutes.',
        'heading' => 'Confirm your e-mail',
        'body' => 'Use the code below to finish creating your account.',
        'expires' => 'The code is valid for :minutes minutes.',
        'ignore' => 'If you did not ask for an account, you can ignore this message.',
    ],
];

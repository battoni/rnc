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

    'meeting' => [
        'footer_reason' => 'You received this e-mail because you booked a meeting in the Tax Reform diagnostic.',
        'when_label' => 'When',
        'with_label' => 'With',
        'length_label' => 'Length',
        'length' => ':minutes minutes, online or at our office',
        'found' => 'The :company diagnostic found :opportunities and :risks. In the meeting the specialist opens each item and the action plan.',
        'opportunity' => '{1} 1 opportunity|[2,*] :count opportunities|[0] no opportunity',
        'risk' => '{1} 1 risk|[2,*] :count risks|[0] no risk',
        'reschedule' => 'Need another time? Reply to this e-mail or call :phone and the team will reschedule.',
        'reschedule_no_phone' => 'Need another time? Reply to this e-mail and the team will reschedule.',
        'sign_off' => 'See you soon,',
        'team' => 'The :firm team',
        'hello' => 'Hello, :name.',
        'hello_anonymous' => 'Hello.',
    ],

    'booking' => [
        'subject' => 'Meeting confirmed: Tax Reform · :when',
        'preheader' => 'Your free meeting with :specialist is booked. Your Impact Report is ready.',
        'heading' => 'Meeting confirmed',
        'body' => 'Your free meeting about the Tax Reform is confirmed.',
        'report_intro' => 'We prepared your Impact Report, with the diagnostic result and the 2026–2033 simulator. It stays available for 30 days.',
        'report_action' => 'Open the Impact Report',
    ],

    'reminder' => [
        'subject' => 'Reminder: your Tax Reform meeting is :when',
        'preheader' => 'Your meeting with :specialist is coming up.',
        'heading' => 'Your meeting is coming up',
        'body' => 'This is the reminder of your meeting about the Tax Reform.',
        'bring' => 'If you can, bring the latest monthly revenue and the list of your main customers and suppliers.',
        'report_action' => 'Review the Impact Report',
    ],

    'referral' => [
        'subject' => 'A referral from :firm: :company',
        'preheader' => ':firm recommends :company to your team.',
        'heading' => 'A referral from :firm',
        'hello' => 'Hello, :partner team.',
        'body' => ':firm recommends a company that took the Tax Reform diagnostic and may need you in :specialty.',
        'company_label' => 'Company',
        'covers_label' => 'Where you can help',
        'message_label' => 'Message from :name',
        'through_firm' => 'The first contact with the company goes through :firm, who introduces you. Reply to this e-mail or reach:',
        'phone_label' => 'Phone',
        'email_label' => 'E-mail',
        'footer_reason' => 'You received this e-mail because you are part of the :firm partner network.',
        'sectors' => [
            'comercio' => 'Retail',
            'industria' => 'Industry',
            'servicos' => 'Services',
            'saude' => 'Healthcare',
            'educacao' => 'Education',
            'agro' => 'Agribusiness',
        ],
    ],
];

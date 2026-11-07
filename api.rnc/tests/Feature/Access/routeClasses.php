<?php

/*
 * Every API route of `routes/api.php` and `routes/api.local.php`, in one of four classes
 * (rnc-2 A2; plan P10, AM2). `RouteMatrixTest` fails when a route is registered and missing here,
 * or listed here and gone — a new route is classified in the phase that adds it.
 *
 *   public  no session: the site, sign-in, registration, the QA login-as (local and testing only)
 *   portal  `permission:portal.access` + `portal`: the partner's own portal
 *   staff   `auth` + a back-office key the role `parceiro` never holds
 *   shared  any signed-in person, partner included: who am I, my name, sign out
 *
 * Keys are `METHOD uri`, without the `api/` prefix; HEAD is left out (it answers as GET).
 */
return [
    // PUBLIC — the site and the doors into the app
    'GET public/firm' => 'public',
    'GET public/sectors' => 'public',
    'GET public/sectors/{slug}' => 'public',
    'POST public/events' => 'public',
    'POST public/diagnostics' => 'public',
    'POST public/diagnostics/{token}/cnpj' => 'public',
    'PATCH public/diagnostics/{token}' => 'public',
    'GET public/diagnostics/{token}/result' => 'public',
    'GET public/diagnostics/{token}/simulation' => 'public',
    'GET public/diagnostics/{token}/availability' => 'public',
    'POST public/diagnostics/{token}/booking' => 'public',
    'GET public/reports/{reportToken}' => 'public',
    'POST auth/login' => 'public',
    'GET auth/support-names' => 'public',
    'POST auth/phone/code' => 'public',
    'POST auth/phone/verify' => 'public',
    'POST auth/register' => 'public',
    'POST auth/email/code' => 'public',
    'POST auth/email/verify' => 'public',
    'GET qa/login-as/{username}' => 'public',

    // SHARED — any signed-in person (AM2)
    'GET auth/me' => 'shared',
    'PATCH auth/me' => 'shared',
    'POST auth/logout' => 'shared',

    // PORTAL — the partner's (rnc-2)
    'GET portal/me' => 'portal',
    'GET portal/referrals' => 'portal',
    'GET portal/referrals/{id}' => 'portal',
    'POST portal/referrals/{id}/transition' => 'portal',
    'GET portal/statement' => 'portal',

    // STAFF — the back office
    'GET auth/permission-groups' => 'staff',
    'GET auth/roles' => 'staff',
    'GET auth/roles/{id}' => 'staff',
    'POST auth/roles' => 'staff',
    'PATCH auth/roles/{id}' => 'staff',
    'PATCH auth/roles/{id}/permissions' => 'staff',
    'DELETE auth/roles/{id}' => 'staff',
    'GET auth/users/check-username' => 'staff',
    'GET auth/users' => 'staff',
    'GET auth/users/{id}' => 'staff',
    'POST auth/users' => 'staff',
    'PATCH auth/users/{id}' => 'staff',
    'PATCH auth/users/{id}/archive' => 'staff',
    'DELETE auth/users/{id}' => 'staff',
    'GET notifications' => 'staff',
    'PATCH notifications/{id}/read' => 'staff',
    'POST notifications/read-all' => 'staff',
    'GET leads' => 'staff',
    'GET leads/{id}' => 'staff',
    'PATCH leads/{id}/stage' => 'staff',
    'POST leads/{id}/notes' => 'staff',
    'GET agenda' => 'staff',
    'GET partners' => 'staff',
    'POST partners' => 'staff',
    'PATCH partners/{id}' => 'staff',
    'GET partners/options' => 'staff',
    'POST partners/{id}/access' => 'staff',
    'POST partners/{id}/access/password' => 'staff',
    'DELETE partners/{id}/access' => 'staff',
    'GET leads/{id}/partner-matches' => 'staff',
    'POST leads/{id}/referrals' => 'staff',
    'GET referrals' => 'staff',
    'GET ledger' => 'staff',
    'PATCH ledger/model' => 'staff',
    'GET metrics/funnel' => 'staff',
    'GET repricing' => 'staff',
    'GET repricing/{id}' => 'staff',
    'GET repricing/{id}/letter' => 'staff',
    'POST repricing/mark' => 'staff',
    'POST clients/import' => 'staff',
    'GET clients/import/template' => 'staff',

    // STAFF — Phase 3 (rnc-3): F1 the sub-brands, `brands.manage`
    'GET brands' => 'staff',
    'PATCH brands/{id}' => 'staff',
    'POST brands/{id}/activate' => 'staff',
    'POST brands/{id}/deactivate' => 'staff',

    // STAFF — Phase 3 (rnc-3): F3a the RNC channel, `channel.read` / `channel.manage`
    'GET channel' => 'staff',
    'POST channel/firms' => 'staff',
    'PATCH channel/firms/{id}' => 'staff',
    'POST channel/firms/{id}/advance' => 'staff',

    // STAFF — Phase 3 (rnc-3): F4 the Reforma assistant, `assistant.ask` / `assistant.manage`
    'POST assistant/ask' => 'staff',
    'GET assistant/suggestions' => 'staff',
    'POST assistant/questions' => 'staff',
    'GET assistant/notes' => 'staff',
    'POST assistant/notes' => 'staff',
    'PATCH assistant/notes/{id}' => 'staff',
    'POST assistant/notes/{id}/review' => 'staff',
    'POST assistant/notes/{id}/retire' => 'staff',
    'GET assistant/gaps' => 'staff',
    // STAFF — Phase 3 (rnc-3): F5 the questions' inbox and the answer, `assistant.ask` + the service
    'GET assistant/questions' => 'staff',
    'POST assistant/questions/{id}/answer' => 'staff',
    // STAFF — Phase 3 (rnc-3): F6 the contract alert, `contracts.manage`
    'GET contracts' => 'staff',
    'POST contracts/import' => 'staff',
    'GET contracts/template' => 'staff',
    'POST contracts/{id}/clause' => 'staff',
    // STAFF — Phase 3 (rnc-3): F8 Reinaldo's briefing, `briefing.read`
    'GET briefing' => 'staff',
    'POST fiscal/imports' => 'staff',
    'GET fiscal/imports' => 'staff',
    'GET fiscal/readiness' => 'staff',
    'GET fiscal/readiness/{id}' => 'staff',
    'POST fiscal/readiness/{id}/notice' => 'staff',
    'GET fiscal/supplier-credit' => 'staff',
    'POST fiscal/supplier-credit/{id}/review' => 'staff',
    'GET radar' => 'staff',
    'GET radar/types/{rule}' => 'staff',
    'POST radar/referrals' => 'staff',
    'GET clients/{id}/events' => 'staff',
];

<?php

/*
 * Project-owned API routes — the cortex sync NEVER touches this file (classified LOCAL).
 *
 * This file is required from routes/api.php, so register your feature-module routes here and
 * they survive every rnc update. Declare your own `use` imports (a required file does not
 * inherit the parent's).
 */

use App\Modules\Booking\Controllers\PublicBookingController;
use App\Modules\Booking\Controllers\PublicReportController;
use App\Modules\Content\Controllers\PublicSectorPageController;
use App\Modules\Diagnostic\Controllers\PublicDiagnosticController;
use App\Modules\Firm\Controllers\PublicFirmController;
use App\Modules\Fiscal\Controllers\NfeImportController;
use App\Modules\Metrics\Controllers\FunnelController;
use App\Modules\Notification\Controllers\NotificationController;
use App\Modules\Partner\Controllers\LedgerController;
use App\Modules\Partner\Controllers\PartnerAccessController;
use App\Modules\Partner\Controllers\PartnerController;
use App\Modules\Partner\Controllers\PortalController;
use App\Modules\Partner\Controllers\ReferralController;
use App\Modules\Pipeline\Controllers\AgendaController;
use App\Modules\Pipeline\Controllers\PipelineLeadController;
use App\Modules\Portfolio\Controllers\ClientImportController;
use App\Modules\Portfolio\Controllers\RepricingController;
use App\Modules\Tracking\Controllers\PublicEventController;
use Illuminate\Support\Facades\Route;

// PUBLIC SITE (rnc-1 F2) — no session needed. The firm is the `X-Firm` header's, or `franco`
// (`ResolveCurrentFirm`); every answer goes through a `Public*` allow-list resource.
Route::get('public/firm', [PublicFirmController::class, 'show']);
Route::get('public/sectors', [PublicSectorPageController::class, 'index']);
Route::get('public/sectors/{slug}', [PublicSectorPageController::class, 'show']);

// Tracking (R7, AM2): 60 events a minute per address — a person browsing never meets it, a loop
// does. Keyed by IP for a visitor (Laravel's `throttle:<max>,<minutes>` signature).
Route::post('public/events', [PublicEventController::class, 'store'])->middleware('throttle:60,1');

// The diagnostic (rnc-1 F3, plan AM3/AM10): by lead token, never by id. Each limiter has its own
// prefix (the third argument): Laravel's plain `throttle:N,M` keys by address alone, so without it
// these routes and the events above would share one counter. The CNPJ lookup is the tight one —
// each call reaches BrasilAPI — at 10 a minute per address.
Route::post('public/diagnostics', [PublicDiagnosticController::class, 'store'])->middleware('throttle:20,1,diagnostic-start');
Route::post('public/diagnostics/{token}/cnpj', [PublicDiagnosticController::class, 'lookupCnpj'])->middleware('throttle:10,1,cnpj-lookup');
Route::patch('public/diagnostics/{token}', [PublicDiagnosticController::class, 'update'])->middleware('throttle:60,1,diagnostic');
Route::get('public/diagnostics/{token}/result', [PublicDiagnosticController::class, 'result'])->middleware('throttle:60,1,diagnostic');
Route::get('public/diagnostics/{token}/simulation', [PublicDiagnosticController::class, 'simulation'])->middleware('throttle:60,1,diagnostic');

// Booking (rnc-1 F4, R15–R17): the slot picker on the same lead token, and the "Relatório de
// Impacto" on its own token (AM4). A booking is a write a loop could lean on: 10 a minute.
Route::get('public/diagnostics/{token}/availability', [PublicBookingController::class, 'availability'])->middleware('throttle:60,1,diagnostic');
Route::post('public/diagnostics/{token}/booking', [PublicBookingController::class, 'store'])->middleware('throttle:10,1,booking');
Route::get('public/reports/{reportToken}', [PublicReportController::class, 'show'])->middleware('throttle:60,1,report');

// The back office's bell (R17): a person's own notifications, behind `notifications.read` (AM8).
$bell = ['auth', 'permission:notifications.read'];
Route::get('notifications', [NotificationController::class, 'index'])->middleware($bell);
Route::patch('notifications/{id}/read', [NotificationController::class, 'markRead'])->middleware($bell)->whereUuid('id');
Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->middleware($bell);

// The team's pipeline (rnc-1 F5, R18–R20, AM8): the board and the lead detail behind `leads.read`,
// the moves and the notes behind `leads.update`, the week's meetings behind `agenda.read`. `{id}` is
// numeric (`NUMERIC_ROUTE_PARAMETERS`).
Route::get('leads', [PipelineLeadController::class, 'index'])->middleware(['auth', 'permission:leads.read']);
Route::get('leads/{id}', [PipelineLeadController::class, 'show'])->middleware(['auth', 'permission:leads.read']);
Route::patch('leads/{id}/stage', [PipelineLeadController::class, 'updateStage'])->middleware(['auth', 'permission:leads.update']);
Route::post('leads/{id}/notes', [PipelineLeadController::class, 'storeNote'])->middleware(['auth', 'permission:leads.update']);
Route::get('agenda', [AgendaController::class, 'index'])->middleware(['auth', 'permission:agenda.read']);

// The network (rnc-1 F6, R21–R23, AM8): the registry behind `partners.manage` (ceo, operador);
// routing on the lead detail with the team's own keys — the scores behind `leads.read`, the
// referral behind `leads.update`; the trail and its filter options behind `referrals.read`.
Route::get('partners', [PartnerController::class, 'index'])->middleware(['auth', 'permission:partners.manage']);
Route::post('partners', [PartnerController::class, 'store'])->middleware(['auth', 'permission:partners.manage']);
Route::patch('partners/{id}', [PartnerController::class, 'update'])->middleware(['auth', 'permission:partners.manage']);
Route::get('partners/options', [PartnerController::class, 'options'])->middleware(['auth', 'permission:referrals.read']);
Route::get('leads/{id}/partner-matches', [ReferralController::class, 'matches'])->middleware(['auth', 'permission:leads.read']);
Route::post('leads/{id}/referrals', [ReferralController::class, 'store'])->middleware(['auth', 'permission:leads.update']);
Route::get('referrals', [ReferralController::class, 'index'])->middleware(['auth', 'permission:referrals.read']);

// A partner's portal login (rnc-2 F1, R2): created, its password regenerated or revoked from the
// registry, behind the registry's own key. The password is in the create and regenerate answers only.
Route::post('partners/{id}/access', [PartnerAccessController::class, 'store'])->middleware(['auth', 'permission:partners.manage']);
Route::post('partners/{id}/access/password', [PartnerAccessController::class, 'regeneratePassword'])->middleware(['auth', 'permission:partners.manage']);
Route::delete('partners/{id}/access', [PartnerAccessController::class, 'destroy'])->middleware(['auth', 'permission:partners.manage']);

// The partner's portal (rnc-2 R3–R4, D2; plan AM1): every route `portal.access` AND `portal`
// (`ResolveCurrentPartner`), which loads the signed-in user's partner inside its firm — the
// operator holds the key and has no partner, so it is refused. Classified in
// `tests/Feature/Access/routeClasses.php`, like every route (A2).
$portal = ['auth', 'permission:portal.access', 'portal'];
Route::get('portal/me', [PortalController::class, 'me'])->middleware($portal);

// The partner's inbox and its moves (rnc-2 F2, R5–R9): only its own referrals — the repository
// filters by `CurrentPartner` — and one step of the graph at a time (`ReferralStatus`).
Route::get('portal/referrals', [PortalController::class, 'referrals'])->middleware($portal);
Route::get('portal/referrals/{id}', [PortalController::class, 'referral'])->middleware($portal);
Route::post('portal/referrals/{id}/transition', [PortalController::class, 'transition'])->middleware($portal);
// The ledger (rnc-2 F4, R11, D10): the partner's own month on the portal; Franco's network month
// behind `ledger.read` (ceo, operador) and the one money model behind the registry's key.
Route::get('portal/statement', [PortalController::class, 'statement'])->middleware($portal);
Route::get('ledger', [LedgerController::class, 'index'])->middleware(['auth', 'permission:ledger.read']);
Route::patch('ledger/model', [LedgerController::class, 'updateModel'])->middleware(['auth', 'permission:partners.manage']);

// Reinaldo's funnel (rnc-1 F7, R24, AM8): the last 7 or 30 days behind `metrics.read` (ceo, operador).
Route::get('metrics/funnel', [FunnelController::class, 'show'])->middleware(['auth', 'permission:metrics.read']);

// Reinaldo's fee repricing (rnc-1 F8, R25–R26, AM8): the portfolio quoted, one client, its letter and
// the batch mark behind `repricing.manage`; the CSV import and its template behind `clients.import`
// (ceo, operador — the team holds neither).
Route::get('repricing', [RepricingController::class, 'index'])->middleware(['auth', 'permission:repricing.manage']);
Route::get('repricing/{id}', [RepricingController::class, 'show'])->middleware(['auth', 'permission:repricing.manage']);
Route::get('repricing/{id}/letter', [RepricingController::class, 'letter'])->middleware(['auth', 'permission:repricing.manage']);
Route::post('repricing/mark', [RepricingController::class, 'mark'])->middleware(['auth', 'permission:repricing.manage']);
Route::post('clients/import', [ClientImportController::class, 'store'])->middleware(['auth', 'permission:clients.import']);
Route::get('clients/import/template', [ClientImportController::class, 'template'])->middleware(['auth', 'permission:clients.import']);
// NF-e import (rnc-2 F6a, R15–R17, D14): one XML (a ZIP from F6b) behind `fiscal.import` (ceo,
// operador), 10 a minute on its own limiter. The raw files stay on the private disk; no route
// serves them (A15).
Route::post('fiscal/imports', [NfeImportController::class, 'store'])->middleware(['auth', 'permission:fiscal.import', 'throttle:10,1,nfe-import']);

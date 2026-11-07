# rnc-2: Build the Franco Reforma Hub Phase 2

**Base:** stacks on rnc-1 — base branch `feature/rnc-1-reforma-hub-pilot` (rnc-1 is not on `main` and the remote is empty; publish with rnc-1).

**Problem.** After Phase 1, Franco's partners could only be reached through the staff side: no partner login, no way to answer a referral, no ledger of what is owed, no chat on the diagnostic, no NF-e data behind the readiness and supplier-credit screens, and the portfolio radar guessed each client's profile. **Outcome.** The real Phase 2 in `api.rnc` + `app.rnc`: partner access and portal (inbox, status graph, SLA), the ledger (commission or subscription), the chat receptionist, NF-e XML import, NF-e readiness, supplier credit, split-payment alert, and the Radar with XML evidence and batch referral.

## R# → tests

| R# | Tests |
| --- | --- |
| R1–R4 partner role, access, login, scoping | `api.rnc/tests/Feature/Access/PartnerAccessTest.php`, `RouteMatrixTest.php`; `Feature/Partner/PortalMeTest.php`, `PartnerUserSeederTest.php`; `app.rnc/e2e/portal/access.spec.ts` |
| R5–R6 inbox, what a partner sees | `Feature/Partner/PortalReferralsTest.php`; `PortalInboxView.view.integration.spec.ts`, `MPortalReferralCard.spec.ts`; `e2e/portal/inbox.spec.ts` |
| R7–R9 status graph, SLA, timeline lines | `tests/Unit/Modules/Partner/ReferralStatusTest.php`, `ReferralSlaTest.php`, `BusinessHoursTest.php`; `MReferralMeetingForm.spec.ts`, `MReferralCloseForm.spec.ts`, `MSlaChip.spec.ts` |
| R10 Franco sees progress | `Feature/Partner/ReferralsTest.php`, `PartnersTest.php` |
| R11 ledger | `Feature/Partner/LedgerTest.php`, `tests/Unit/Modules/Partner/LedgerServiceTest.php`; `PortalStatementView.view.integration.spec.ts`, `LedgerView.view.integration.spec.ts`, `useLedgerMonth.spec.ts` |
| R12–R14 chat receptionist and channel | `Feature/Diagnostic/ChatChannelTest.php`; `MChatReceptionist.spec.ts`, `useChatReceptionist.spec.ts`; `e2e/diagnostic/chat.spec.ts` |
| R15–R17 NF-e import | `Feature/Fiscal/NfeImportTest.php`, `NfeImportFolderTest.php`, `NfeSeederTest.php`; `tests/Unit/Modules/Fiscal/AccessKeyTest.php`, `NfeXmlParserTest.php`; `MNfeImportForm.spec.ts`, `NfeImportView.view.integration.spec.ts` |
| R18–R20 readiness, notice | `Feature/Fiscal/ReadinessTest.php`; `ReadinessView.view.integration.spec.ts`, `MClientNoticeDialog.spec.ts`; `e2e/fiscal/readiness.spec.ts` |
| R21–R23 supplier credit, split payment | `Feature/Fiscal/SupplierCreditTest.php`; `SupplierCreditCalculatorTest.php`, `SplitPaymentCalculatorTest.php`; `SupplierCreditView.view.integration.spec.ts` |
| R24–R27 Radar, evidence, batch referral | `Feature/Radar/RadarSummaryTest.php`, `RadarBatchReferralTest.php`; `EvidenceBuilderTest.php`; `RadarView.view.integration.spec.ts`, `MRadarTypeDetail.spec.ts`; `e2e/radar/radar.spec.ts` |
| R28 client consent | `Feature/Radar/RadarBatchReferralTest.php` |
| R29 seeds | `PartnerSeederTest.php`, `PartnerUserSeederTest.php`, `NfeSeederTest.php` |

## Review

Plan review (fable-reviewer, before F1): amendments AM1–AM13 in `plan.md`.
Branch review (fable-reviewer): API 5 findings and SPA 7 findings, applied in 3415b88. **Not applied:** the rejected findings, each with its reason, in W118–W125 of `state.md`.

## QA

- Layer 1, tests (this worktree, Node 24): 1291 Pest · 1113 Vitest · 144 Playwright, all green. Earlier commits 152286c…f7751df and 058b6ec carry wrong Pest/Playwright counts copied from another worktree; these are the true ones.
- Layer 2, Chrome (07/11, API :8015 + SPA :5177 + seeded SQLite): R1 portal access 14/16 · R2 inbox/SLA 16/16 · R3 ledger 9/9 money steps · R4 chat 23/24 · R5 fiscal 18/18 · R6 Radar 10/10 · R7 access sweep 11/11 + mobile drawer OK · R8 retest 4/4. Three bugs found and fixed: the funnel ignored chat diagnostics started without the CTA beacon (058b6ec, W126); the firm phone showed "+1137325500" (8e8dd29); an operator at `/parceiro` saw a load error (8e8dd29, W127). The script errors were in the script, not the product.
- Layer 3, a person: owed.

## Decided while you were away

W1–W127 in `specs/rnc-2-reforma-hub-phase-2/state.md` → "Decided while you were away"; spec changes C1–C4 in `spec.md` → "Changes while building".

🤖 Generated with [Claude Code](https://claude.com/claude-code)

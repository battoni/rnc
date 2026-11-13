# rnc-3: Build the Franco Reforma Hub Phase 3

**Base:** stacks on rnc-2 — base branch `feature/rnc-2-reforma-hub-phase-2` (rnc-1 and rnc-2 are not on `main` and the remote is empty; publish after them).

**Problem.** After Phase 2 the Franco site had one face and one voice: no sub-brands, no way to see Franco's revenue from the Rede Nacional de Contabilidade channel, no assistant for the team's Reforma questions, no view of client contracts missing the Reforma clause, and no daily summary for the CEO. **Outcome.** The real Phase 3 in `api.rnc` + `app.rnc`: sub-brands that re-theme the public site, the RNC channel screen, the curated Reforma assistant with specialist review, the contract alert with referral to a partner, and the daily briefing (screen and 07:00 mail).

## R# → tests

| R# | Tests |
| --- | --- |
| R1–R4 sub-brands, public firm, theming, lead brand | `api.rnc/tests/Feature/Firm/PublicBrandTest.php`, `BrandRepositoryFirmTest.php`; `Feature/Diagnostic/BrandLeadTest.php`; `Feature/Pipeline/BrandFilterTest.php`; `app.rnc/src/modules/Site/composables/brandPalette.spec.ts`, `usePublicFirm.spec.ts`; `e2e/site/brands.spec.ts` |
| R5 brands console | `Feature/Firm/BrandAdminTest.php`; `tests/Unit/Modules/Firm/ContrastTest.php`; `MAddEditBrandForm.spec.ts`, `BrandsView.view.integration.spec.ts` |
| R6–R9 RNC channel | `Feature/Channel/ChannelTest.php`; `tests/Unit/Modules/Channel/ChannelShareTest.php`, `ChannelStageTest.php`; `MAddEditChannelFirmForm.spec.ts`, `ChannelView.view.integration.spec.ts`; `e2e/channel/channel.spec.ts` |
| R10–R11 knowledge base, review, expiry | `Feature/Assistant/NoteReviewTest.php`, `NoteExpiryTest.php`; `KnowledgeView.view.integration.spec.ts`, `MAddEditNoteForm.spec.ts` |
| R12–R13 ask, matcher, no-note answer | `Feature/Assistant/AskTest.php`; `tests/Unit/Modules/Assistant/NoteMatcherTest.php`; `AssistantView.view.integration.spec.ts`, `MAnswerCard.spec.ts`; `e2e/assistant/assistant.spec.ts` |
| R14–R15 specialist questions, gaps | `Feature/Assistant/QuestionHandoffTest.php`, `QuestionAnswerTest.php`, `AssistantGapsTest.php`; `QuestionsView.view.integration.spec.ts` |
| R16 driver | `Feature/Assistant/AssistantDriverTest.php` |
| R17–R19 contracts, CSV import, alert | `Feature/Portfolio/ContractImportTest.php`, `ContractAlertTest.php`; `tests/Unit/Modules/Portfolio/ContractExposureTest.php`; `MImportContractsForm.spec.ts`, `ContractsView.view.integration.spec.ts`, `MContractRow.spec.ts`; `e2e/contracts/contracts.spec.ts` |
| R20–R22 legal referral, partner view, clause | `Feature/Partner/ContractReferralTest.php`, `PortalContractReferralTest.php`; `Feature/Portfolio/ContractClauseTest.php`; `tests/Unit/Modules/Partner/ContractValueBandTest.php` |
| R23–R26 briefing, pipeline value, actions, screen | `Feature/Briefing/BriefingTest.php`; `tests/Unit/Modules/Briefing/FeeQuartilesTest.php`, `ActionPickerTest.php`; `BriefingView.view.integration.spec.ts`, `MCountdown.spec.ts`, `Briefing/routes.spec.ts`; `e2e/briefing/briefing.spec.ts` |
| R27 briefing mail | `Feature/Briefing/DailyBriefingCommandTest.php`, `BriefingMailActionTest.php` |
| R28 permission keys, matrix, isolation | `Feature/Access/Phase3AccessTest.php`, `NoOutwardClientsTest.php`; `Feature/StrayRequestGuardTest.php`; `Contracts/routes.spec.ts`, `Home/views/Workspace/routes.spec.ts` |
| R29 seeds | covered across the feature tests above and the seeded Chrome QA (R1–R6) |

## Review

Plan review: Sonnet AM1–AM14, fable-reviewer AM15–AM23 (in `plan.md`).
Branch review (fable-reviewer): API 4 lows, SPA 1 medium and 4 lows, applied in 27d674e. **Not applied:** the rejected findings, each with its reason, in W113–W117 of `state.md`.

## QA

- Layer 1, tests (this worktree, Node 24): 1851 Pest · 1289 Vitest · 180 Playwright, all green.
- Layer 2, Chrome (07/11, API :8016 + SPA :5178 + seeded SQLite): R1 brands 25/26 (1 blocked by the browser's pop-up blocker, covered by the Vitest for S1) · R2 briefing 13/13 · R3 assistant 17/18 (the 1 is by design: "última em" is hidden below 640px) · R4 contracts 17/17 · R5 RNC channel 7/10 (3 blocked by tooling, covered by Pest ChannelShareTest / ChannelStageTest / ChannelTest) · R6 access sweep 12/12. One bug found and fixed: the brand colour error stayed after the colour changed (9f0766b, W118).
- Layer 3, a person: owed.

## Decided while you were away

W1–W118 in `specs/rnc-3-reforma-hub-phase-3/state.md` → "Decided while you were away"; spec changes C1–C9 in `spec.md` → "Changes while building".

🤖 Generated with [Claude Code](https://claude.com/claude-code)

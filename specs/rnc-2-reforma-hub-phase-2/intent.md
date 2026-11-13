# Intent: Franco Reforma Hub — Phase 2 (client data and the partner network)
Author: Guilherme Battoni (decided by Claude while the owner was away, 07/11/2026) · Status: approved 07/11/2026
ID: rnc-2 · Branch: feature/rnc-2-reforma-hub-phase-2 (from feature/rnc-1-reforma-hub-pilot) · Track: feature

## Problem
Phase 1 (`rnc-1`) put the funnel, the pipeline, the governance trail and the fee repricing in a real
product, but it stops at Franco's door: a referred partner gets an e-mail and nothing else, the
visitor has only the form, and the 500 clients are judged on four profile fields alone. Phase 2 of
the commercial menu ("client data", `battonidev-1` `commercial.md` §1, 137 h in §3) is what turns
the NF-e XMLs Franco already receives for its clients into evidence, and the referral into a
tracked hand-off. NF-e IBS/CBS fields are mandatory since 03/08/2026 and CBS starts 01/01/2027
(`facts.md` §2), so the readiness and credit work is worth most before January.

## Proposed outcome
The six Phase 2 modules of the menu, built on Phase 1's code: **partner portal and ledger** (a
partner logs in, sees only what Franco referred to it, moves it contato → reunião → proposta →
fechado against an SLA, and sees what it owes Franco) · **chat receptionist** (a guided conversation
on the site reaching the same diagnostic, result and booking, the lead tagged "chat") · **NF-e
readiness** (per client: invoices in the period, share with the IBS/CBS group, invoices at risk of
rejection 1115, status ok · atenção · crítico) · **supplier credit** (purchases by supplier regime and
the credit lost from 2027) · **split-payment alert** (cash held back per month) · **Radar with XML
evidence** (the rules over the 500 clients, each item backed by what the XMLs show, batch referral
with consent).

## Affected users and systems
- Partner (new role): one login per partner record, its own portal, nothing else.
- Equipe Franco (NF-e readiness, supplier credit, the client warnings), Reinaldo (Radar, split
  payment, the ledger, the partner logins), Operador (everything).
- Visitor (the chat on the public site).
- Franco's NF-e XMLs: imported by upload; no SEFAZ, bank or LLM call.

## Constraints
- Everything Phase 1's intent says still holds: Franco's brand on top, Willy and APW nowhere, Franco
  talks to every lead first, no real personal data in seeds, figures are estimates as ranges, Reforma
  statements dated and citing `facts.md`, local only (`MAIL_MAILER=log`, no deploy, no push to `main`).
- A partner sees only the leads and clients referred to it, with consent; never another partner's,
  never a lead's data beyond what the referral carries; firm isolation holds for partners too.
- No real third-party call anywhere, in tests or by default: an LLM, SEFAZ/NF-e distribution and
  banks sit behind an interface with a deterministic local driver.
- XMLs are client tax data: stored in the firm's private storage, never public, never in git;
  seeded XMLs are generated with invented CNPJs.

## Out of scope
Phase 3 (contract alert, Express/Prime, RNC channel, Reinaldo's briefing, the Reforma assistant).
Fetching XMLs from SEFAZ (DF-e distribution with a digital certificate), a real LLM, bank or payment
integrations, WhatsApp sending, hosting, a partner-facing mobile app, paying or invoicing commissions.

## Open questions (product choices, resolved in the spec)
Partner login and onboarding; the ledger's money model (commission vs monthly, `battonidev-1` D18 left
both); the chat's engine (scripted vs an LLM); where the XMLs come from and how a client is matched;
the readiness thresholds; who sees which fiscal screen.

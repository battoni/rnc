# Intent: Franco Reforma Hub — Phase 3 (growth)
Author: Guilherme Battoni (decided by Claude while the owner was away, 07/11/2026) · Status: approved 07/11/2026
ID: rnc-3 · Branch: feature/rnc-3-reforma-hub-phase-3 (from feature/rnc-2-reforma-hub-phase-2 after its F1) · Track: feature

## Problem
Phases 1 and 2 (`rnc-1`, `rnc-2`) turn Franco's site into leads and its NF-e XMLs into evidence, but
they stop at what Franco already sells. Phase 3 of the commercial menu ("growth", `battonidev-1`
`commercial.md` §1, 185 h in §3) is what grows the business beyond the funnel: the long client
contracts that span the 2027–2033 transition with no price clause (`facts.md` §2, "Everyone"),
Franco's own sub-brands Express and Prime (`facts.md` §1, Brands), Franco as the channel that brings
other RNC member firms to the platform (`facts.md` §1, RNC member; ~15 % per firm, `commercial.md`
§1–2), a one-minute daily briefing for Reinaldo, and a staff assistant that answers Reforma
questions only from Franco's own reviewed notes. CBS starts 01/01/2027 (`facts.md` §2), so every
contract signed or renewed now already crosses it.

## Proposed outcome
The five Phase 3 modules of the menu, built on Phases 1–2: **contract repricing alert** (client
contracts that run into 2027–2033 without a Reforma price clause, ranked by exposure, sent to a
covering legal partner with consent) · **Express and Prime** (two sub-brand tenants of Franco, their
own public look, sharing Franco's data and network) · **RNC channel** (invented RNC member firms
moving through a pipeline, Franco's channel share shown per firm and in total, nothing billed) ·
**Reinaldo's briefing** (one screen: what came in, what is due today, what is late, what to do
first, the countdown) · **Reforma assistant** (staff ask a question and get an answer drawn only
from reviewed, dated notes, each citing its source, reviewer and date — or no answer and a hand-off
to a specialist).

## Affected users and systems
- Reinaldo (contracts, briefing, assistant notes review), Equipe Franco (contracts read, assistant),
  Operador (tenants, RNC channel, everything).
- Partners (a legal referral reaches the portal of Phase 2 like any other).
- Visitors of the Express and Prime sites (a re-themed public site; leads land in Franco's pipeline).
- No outside system: no LLM, no video host, no RNC system, no contract signature service.

## Constraints
- Everything Phases 1–2 hold still holds: Franco's brand on top, Willy and APW nowhere; Franco talks
  to every lead first and partners see only consented referrals; no real personal data in seeds;
  every figure an estimate shown as a range; every Reforma statement dated and citing `facts.md`;
  local only (`MAIL_MAILER=log`, no deploy, no push to `main`).
- The assistant never invents: an answer comes only from a reviewed note with a source and a date;
  anything else is "I don't know" plus a hand-off. Any model behind it is off by default, needs a key
  that does not exist locally, and is never reached by a test.
- Contract data is client data: firm-scoped, never public, never in git; seeded contracts invented.
- Express and Prime are Franco (same firm, same data, same network); another firm's data never shows.
- RNC figures are a channel estimate (`commercial.md` §2), labelled "a conferir — sem cobrança".
- Every new role or permission gets route-matrix and isolation tests.

## Out of scope
A real LLM or embedding service on by default, contract upload with text extraction (OCR/PDF parsing),
e-signature, video hosting or recording, billing RNC firms or paying Franco's share, real RNC data,
separate databases per sub-brand, Express/Prime own domains (hosting), WhatsApp sending.

## Open questions (product choices, resolved in the spec)
Where contracts come from (seed, CSV, a form); the ranking formula; who receives "Encaminhar ao
jurídico"; whether Express/Prime are firms or brands of one firm; where an RNC firm's data lives and
whether it gets a login; what the briefing's "top 3 actions" are; the assistant's engine (curated
answers vs retrieval vs a model), who reviews a note and how a stale note is shown.

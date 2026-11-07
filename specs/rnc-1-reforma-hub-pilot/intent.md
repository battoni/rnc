# Intent: Franco Reforma Hub — the real pilot (Phase 1)
Author: Guilherme Battoni (decided by Claude while the owner was away, 07/11/2026) · Status: approved 07/11/2026
ID: rnc-1 · Branch: feature/rnc-1-reforma-hub-pilot · Track: feature

## Problem
The Reforma Hub exists only as a clickable demo with simulated data
(`battoni.dev/dev/demos/franco-reforma-hub`, spec `battonidev-1`). Franco Contabilidade still has
no diagnostic, booking, lead pipeline or metrics (`facts.md` §1), and CBS starts on 01/01/2027.
To sell the December pilot (`battonidev-1` `commercial.md` §2–3) and run it, the product must
exist for real: an API, a database, logins, real e-mail, real CNPJ lookup.

## Proposed outcome
A working product in this repo (`api.rnc` Laravel + `app.rnc` Vue), Phase 1 of the commercial
menu: foundation · site · diagnostic · result · booking · pipeline · metrics · governance · fee
repricing. A visitor runs the diagnostic on Franco's site, is captured as a lead before the result,
books a specialist; Franco's team works the lead on a kanban and refers it to a partner only with
consent; Reinaldo sees the trail and the repricing of his client base; the operator sees the funnel.

## Affected users and systems
- Visitor (public, pt-BR, no login).
- Equipe Franco (reception/commercial and specialists), Reinaldo (CEO), Operador (Battoni Dev).
- Partners exist as records (referral targets); their own portal is Phase 2.

## Constraints
- Franco's brand on top (navy `#00375a`, cyan `#14c3ff`); Willy and APW appear nowhere.
- Franco talks to every lead first; a partner gets a lead only through Franco's referral, with consent.
- Lead (company + phone or e-mail) captured before the result.
- Reforma statements dated and defensible (`facts.md` §2); every figure an estimate, as a range.
- No real personal data in seeds; demo CNPJs invented except where a real lookup is the point.
- Local only: no deploy, no real e-mail sent (`MAIL_MAILER=log`), no push to `main`.

## Out of scope (later phases of the menu)
Partner portal and login, chat receptionist, NF-e readiness, supplier credit, split payment, Radar
with XML evidence (Phase 2); contract alert, Express/Prime, RNC channel, Reinaldo's briefing,
the Reforma assistant (Phase 3). Hosting, domains, ads, WhatsApp sending.

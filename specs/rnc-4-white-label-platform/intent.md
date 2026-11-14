# Intent: Reforma hub as a white-label platform for accounting firms
Author: Guilherme Battoni (decisions taken by him on 13/11/2026; written by Claude in away mode) · Status: approved 13/11/2026 (delegated: "Go ahead and make it happen! Work it out.")
ID: rnc-4 · Branch: feature/rnc-4-white-label-platform (from main 33dad53 + 3 tooling commits) · Track: feature

## Problem
`rnc-1`–`rnc-3` built the Reforma Tributária hub for one firm, Franco Contabilidade, and Franco did
not commit to the pilot. The code is multi-firm in its tables (`firm_id` everywhere) but not in its
face: the firm comes from a header defaulting to `franco`, the back office is painted at build time
with Franco's theme, e-mails carry a global "Franco Contabilidade" brand, the consent sentence names
Franco's network, routes and roles are called "Reinaldo", and only a database edit creates a firm.
The owner and his business consultant decided to sell the hub as a **white-label subscription to
accounting firms** riding the Reforma (CBS starts 01/01/2027): a "beta" with about two paying firms
for about three months at a promo price, then marketing and direct outreach. Today the product
cannot be shown to a second firm without showing Franco, and cannot take a second firm at all.

## Proposed outcome
- Any number of accounting firms run on one deploy, each on its own subdomain, each seeing only its
  own data, its own look, its own name — never Franco's, never another firm's.
- The product has a name held in one place (a placeholder until the brand is chosen).
- Battoni Dev operates the platform from above every firm: creates a firm, sets its look and its
  modules, gives its manager a login, and enters a firm only through an audited, visible "view as".
- Each firm chooses which modules it pays for; the core funnel is always on.
- A fictional demo firm shows the product to prospects with invented data.
- The root domain is the product's own site for accounting firms with a "join the beta" signup that
  lands on the product's own pipeline (dogfooding).
- A firm's manager keeps the firm's contact data, team, specialists' hours and consent text, every
  consent edit a new version.
- The code is ready to deploy (runbook, env inventory, health check), but nothing is deployed.

## Affected users and systems
Visitors of a firm's site (diagnostic, booking, report); visitors of the product site (accounting
firms); a firm's manager (today's `ceo` role) and team (`equipe`, specialists); the network's
partners (portal); Battoni Dev as platform operator. Both sub-projects (`api.rnc`, `app.rnc`), the
e-mails, the seeders, the Playwright suite (it serves one host today).

## Constraints
- Cross-firm isolation is absolute: a row, a session, a login or an e-mail of firm A never reaches
  firm B; an unknown thing is a 404.
- Franco stays a working tenant (slug `franco`), RNC channel and Express/Prime included.
- The business consultant and his firm never appear in the product (owner); no real firm's data
  is shown to another prospect (the demo is fictional).
- Generic intake-funnel CRM for any business is NOT the market now; vertical content stays separable
  only as an architecture principle.
- pt-BR UI; no horizontal scroll at 390–1920 px; WCAG contrast 4.5:1 on every firm palette.
- No deploy, no push to `main`; migrations stay folded into `create_*` until the first deploy.
- Personal data: invented examples only, in seeds, tests and these files.

## Out of scope
Custom domains per firm; billing and subscription payments; per-firm sending domains for e-mail;
a generic (non-accounting) funnel; translating the product site to English; the deploy itself.

## Open questions
None left for the owner: his 12 decisions of 13/11/2026 are in the coordinator's brief, quoted in
`spec.md` → Decisions; the remaining small choices are taken in the spec as D# with how to reverse.

# Spec: Franco Reforma Hub — the real pilot (Phase 1)
Status: closed 07/11/2026 (decided by Claude in the owner's absence, every D# reversible) · ID: rnc-1 · Intent: approved 07/11/2026
Proposal: none — the behaviour reference is the approved demo `battonidev-1`
(`/Users/battoni/Work/battoni.dev/dev/demos/franco-reforma-hub`, spec
`/Users/battoni/Work/battoni.dev/dev/specs/battonidev-1-reforma-hub/spec.md`). Where this spec is
silent, do what the demo does. Copy wording, rules and numbers from it; do not copy its code style
(it is vanilla JS — here the code follows this repo's rules).

## Requirements

**Foundation**
- R1. Firms (tenants): a `firms` table with name, slug, brand (primary, accent, logo text), public
  contact (address, phone, mailbox) and business hours. Franco Contabilidade is firm 1, seeded from
  `facts.md` §1. Every business table carries `firm_id`; a query never crosses firms (D1).
- R2. Roles: `operador` (Battoni Dev, every firm), `ceo` (Reinaldo), `equipe` (Franco's team; a
  flag `is_specialist` makes a member bookable). Login by e-mail + password (bootstrap Auth module).
  Seeded users per role, passwords in `state.md` → Local environment (D2).
- R3. Two shells in `app.rnc`: the public site (no login, Franco's brand, pt-BR) and the back
  office (login, a rail filtered by role: Equipe · Reinaldo · Operador). Willy/APW appear nowhere.
- R4. The bootstrap's sample modules that this product does not use (`Idea`, `Tip`, `Showcase`,
  `Home` sample content) are removed with their full test surface (`/battoni-setup-project` Step 2c).

**Site**
- R5. Landing `/`: hero with CTA to the diagnostic, countdown to 01/01/2027 (CBS), proof points
  35 anos · 500+ clientes · ISO 9001 · RNC, the "o que muda" strip and the 2026–2033 timeline
  (`facts.md` §2). Footer with Franco's public contact.
- R6. Six sector pages `/reforma/<setor>` (comércio, indústria, serviços, saúde, educação, agro),
  each with ≥ 3 dated facts and the diagnostic CTA. Content lives in the API (firm-scoped) and is
  served by a public endpoint.
- R7. Tracking: an anonymous visitor id (first-party, localStorage) and the source (utm_source /
  utm_medium / referrer → orgânico · pago · parceiro · direto) are sent with every page view, CTA
  click and diagnostic step to a public, rate-limited `POST /api/public/events`.
- R8. Each public page sets its title, description and Open Graph tags.

**Diagnostic and result**
- R9. CNPJ path: the visitor types a CNPJ; the API validates the check digits and looks it up on
  BrasilAPI (`/api/cnpj/v1/{cnpj}`) with a 5 s timeout and a 30-day cache, mapping razão social,
  CNAE → sector, porte → faturamento band, and `opcao_pelo_simples` / MEI → regime when known.
  The UI shows the "consultando Receita · Simples · CNAE" sequence while it waits. A failure or an
  unknown CNPJ falls back to the quiz with a friendly message, never an error page (D3).
- R10. Quiz path: sector, regime (Simples · Presumido · Real · não sei), faturamento band, B2B share,
  incentivos de ICMS, exportação.
- R11. Progressive capture: right after step 1 the visitor gives company name and one contact
  (WhatsApp/phone or e-mail, validated) plus the optional consent to be connected to Franco's
  partners (text versioned and stored with the answer, the time and the channel). From that moment
  the lead exists as "Lead parcial" with its last step, even if the visitor leaves.
- R12. Rules engine on the API: the profile maps to opportunities and risks from the `facts.md` §2
  statement table, each item citing its rule, with an R$ range scaled by faturamento band — the
  demo's `diagnose()` rules ported to PHP, unit-tested item by item.
- R13. Result: "Encontramos N oportunidades e M riscos" with titles and R$ ranges; details stay
  locked behind the meeting (the public endpoint never returns them).
- R14. Impact simulator: the 2026–2033 tax mix year by year for the profile (the demo's `taxMix()`
  and rates, W6 of `battonidev-1`) and the split-payment cash effect, all labelled "estimativa".

**Booking and notifications**
- R15. Availability: each specialist has weekly hours (default Franco's: seg–qui 8–18, sex 8–17),
  slots of 30 min, the next 10 working days, minus booked slots and Brazilian national holidays.
  One specialist is marked "Indicado para o seu perfil" (demo `suggestedSpecialist()`).
- R16. Booking: the visitor picks a slot; the API books it in a transaction with a unique
  (specialist, start) constraint, so two visitors cannot take the same slot (the second gets
  "esse horário acabou de ser reservado"). The lead moves to "Reunião marcada".
- R17. E-mail: the visitor receives a branded confirmation (Mailable, queued) with a link to their
  "Relatório de Impacto" page (signed URL, valid 30 days); a reminder goes 24 h before
  (scheduled command). Franco's team receives an in-app notification (database notifications, a
  bell with unread count, polled every 30 s) for each new lead and each booking.

**Pipeline**
- R18. Kanban: Visitante → Lead parcial → Lead → Reunião marcada → Proposta → Cliente, plus
  Perdido (with a reason). Cards move by drag or by button; each move is an event on the lead's
  timeline with who and when.
- R19. Lead detail: profile, the full diagnostic (details unlocked for staff), the timeline, notes,
  contact actions as real links (`tel:`, `mailto:`, `https://wa.me/`), consent status.
- R20. Agenda: the team's working week with the booked meetings; ◀ ▶ moves the week.

**Governance (network)**
- R21. Partners: a firm-scoped registry (name, specialty from the 8 of `facts.md` §3, contact,
  active), managed by `ceo` and `operador`. Seeded with the 8 invented placeholder partners of the
  demo, marked "(exemplo)".
- R22. Routing: on the lead detail, each partner gets a match score against the diagnostic (demo
  `matchPartners()` / `PARTNER_RULES`) and an "Encaminhar" with a message. Franco stays the
  lead's owner. A lead without consent cannot be referred: the button is disabled with the reason,
  and the API refuses it (422) too.
- R23. Trail (`ceo`, `operador`): every referral — lead, partner, consent given (yes, with the
  stored text and time), who referred, when, status — newest first, filterable by partner.

**Metrics**
- R24. Funnel for 7 or 30 days: visitas → clique CTA → início diagnóstico → contato capturado →
  resultado → agendou → cliente, each step ≤ the previous one, with drop-off, a table by source and
  a table by sector page. Counts are distinct visitors (or leads) from the events of R7 and the
  pipeline of R18.

**Fee repricing**
- R25. Client portfolio: Franco's clients (name, CNPJ, sector, regime, band, B2B share, monthly fee)
  imported from a CSV (template downloadable; row errors reported by line, nothing half-imported)
  and seeded locally with 500 invented clients.
- R26. Repricing (`ceo`): the rules engine runs on each client; each opportunity weighs 1,5 points
  and each risk 2,5, the uplift is clamped to 4–25 %, the new fee rounded to R$ 50 (demo W32/W43).
  The screen shows the yearly uplift total, a filter by sector and regime, a client detail with its
  items, "Gerar carta de reajuste" (a branded, printable letter for that client) and "marcar em
  lote" for the selected clients (stored with who and when).

## Acceptance criteria
Layers: `→ Pest` (API feature/unit), `→ Vitest` (SPA unit/integration), `→ E2E` (Playwright, API
mocked), `→ Chrome` (the battoni-test-in-chrome round against the real API and database).
- A1 (R1): a user of firm A never reads, lists or updates a lead, client or partner of firm B (404). → Pest
- A2 (R2, R3): each role logs in and sees only its rail groups; an `equipe` user gets 403 on the
  trail, the repricing and the metrics endpoints. → Pest + Vitest
- A3 (R5, R6, R8): landing and the 6 sector pages render with Franco's brand, the countdown, ≥ 3
  dated facts, the CTA and their own title. → E2E + Chrome
- A4 (R7, R24): page view + CTA + diagnostic start + contact + result + booking from one visitor
  raise each funnel step by 1; each step ≤ the previous; 7 vs 30 days differ on the seed. → Pest
- A5 (R9): a valid CNPJ with BrasilAPI faked fills name, sector, band and Simples; an invalid check
  digit is a 422; a BrasilAPI timeout or 404 returns the quiz fallback. → Pest (Http::fake) + Vitest
- A6 (R11): contact given after step 1 and the visitor gone → a "Lead parcial" with its last step
  is on the kanban; the consent record holds text, time and channel. → Pest + Chrome
- A7 (R12, R13): profile "Simples, B2B 80 %, serviços" gives the Simples regular-regime option and
  N ≥ 3; the public result has titles and ranges and no detail field. → Pest
- A8 (R14): the simulator returns 8 years and 2033 has no PIS/Cofins/ICMS/ISS. → Pest + Vitest
- A9 (R15, R16): a booked slot disappears from the availability; a second booking of the same slot
  is refused; holidays and weekends have no slots. → Pest
- A10 (R17): booking queues the confirmation Mailable to the lead's e-mail with a signed report
  link; the reminder command sends only for meetings in the next 24 h, once; staff get a database
  notification. → Pest (Mail::fake, Notification::fake)
- A11 (R18, R19): moving a card logs the move on the timeline; moving to Cliente raises the funnel's
  "cliente"; Perdido requires a reason. → Pest + Vitest
- A12 (R22): referring a lead without consent → 422 and a disabled button with the reason; with
  consent → the referral is in the trail with the consent text. → Pest + Vitest
- A13 (R25): a CSV with one bad row imports nothing and names the line; a good CSV imports all. → Pest
- A14 (R26): the uplift total equals Σ (suggested − current) × 12 over the filtered clients; every
  pct is within 4–25 % after rounding; the letter names the client. → Pest
- A15 (all): every screen renders without a console error and with no horizontal scroll at 390 px
  and 1440 px; no "Willy" or "APW" anywhere. → Chrome

## Decisions (taken by Claude on 07/11/2026, owner away; each says how to reverse)
- D1 Tenancy → `firm_id` on every business table from day one, through a `BelongsToFirm` trait and
  global scope: Phase 3 (Express/Prime, RNC firms) needs it, and adding it later rewrites every
  query. Reverse: drop the scope; the column stays harmless.
- D2 Login → a password login (see D9), not the e-mail code flow: works locally without a mail
  server.
- D3 CNPJ → the real BrasilAPI lookup (free, no key) instead of the demo's simulation, because the
  product is real now; regime beyond Simples/MEI is not public, so it is asked. Reverse: the
  `CnpjLookup` contract has a fake implementation.
- D4 Site → the public pages live in `app.rnc` (one SPA, two layouts), not a separate Astro site:
  one deploy and one API for the pilot. Server-side rendering for SEO is a known gap (RULES.md).
- D5 Notifications → e-mail through Laravel Mail (log driver locally) and in-app database
  notifications polled every 30 s; no websockets, no WhatsApp in Phase 1.
- D6 Report → the "Relatório de Impacto" is an HTML page behind a signed URL, printable, not a PDF
  attachment: no PDF library, and the page can be updated. Reverse: render it with a PDF package.
- D7 Partners have no login in Phase 1 (the portal is Phase 2); a referral is recorded and the
  partner is notified by e-mail.
- D8 Database → MySQL 8 locally (database `rnc`) for the running app; Pest on in-memory SQLite
  (the bootstrap's `phpunit.xml`), so every migration must run on both.
- D9 Login → the bootstrap's `username` (e-mail/username + password) flow, the one the e2e suite
  already targets (`.env.test`). Reverse: `VITE_LOGIN_FLOW`.

## Out of scope
Everything in intent "Out of scope". No deploy, no real e-mail delivery, no SSR.

## Changes while building
- C1 (F1, W3): the back office's landing is `/painel` (route `home`); `/` is the public site for everyone, signed in or not. The rail shows Equipe · Reinaldo · Operador by permission (`leads.read`, `metrics.read`, `users.read`), and Operador holds Usuários and Perfis.
- C2 (F2, W12): until F3, every CTA of the site lands on `/diagnostico`, a placeholder that says the diagnostic opens soon and shows Franco's phone and mailbox. F3 replaces it under the same route.
- C3 (F2, W15): R7's source mapping is concrete — paid `utm_medium` or an ad click id → pago; partner `utm_medium` → parceiro; any other UTM tag or an external referrer → orgânico; nothing → the visit's earlier source in this session, else direto. A visitor keeps the source of its first event.
- C4 (F3, W20): until F4, the result's "Agendar com um especialista" opens a holding step that says the online agenda opens soon and shows Franco's phone and mailbox; F4 replaces it with the slot picker.
- C5 (F3, W21, W27): R10's regimes stay four — a MEI found on BrasilAPI is read as Simples. "Não sei" matches no regime rule (sector rules and the two always-rules still apply). The result turns only a lead parcial into a lead; a profile without sector, regime and band has no result (422).

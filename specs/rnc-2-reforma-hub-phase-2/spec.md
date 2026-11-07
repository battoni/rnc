# Spec: Franco Reforma Hub — Phase 2 (client data and the partner network)
Status: closed 07/11/2026 (decided by Claude in the owner's absence, every D# reversible) · ID: rnc-2 · Intent: approved 07/11/2026
Proposal: none — the behaviour reference is the approved demo `battonidev-1`
(`/Users/battoni/Work/battoni.dev/dev/demos/franco-reforma-hub/src/` — `views/partner.js`,
`views/chat.js`, `views/fiscal.js`, `views/ceo.js` (Radar), `engine.js`, `seed.js`) and its spec
`/Users/battoni/Work/battoni.dev/dev/specs/battonidev-1-reforma-hub/spec.md` (R14, R20, R21, R23,
R29–R31, R33). Where this spec is silent, do what the demo does; copy its wording, rules and
numbers, never its code style. The demo has **no XML evidence and no partner access control** at
all: those are designed here (D5–D9, D2). Builds on `rnc-1` (`specs/rnc-1-reforma-hub-pilot/`),
whose rules, amendments AM1–AM15 and changes C1–C11 all still hold.

## Requirements

**Partner access (portal foundation)**
- R1. A new role `parceiro` holds only the portal keys (`portal.access`). A partner login is a
  `users` row with `partner_id` set; one partner record has at most one active login (D1).
- R2. Whoever holds `partners.manage` (Reinaldo, the operator) creates a partner's access from the
  partners screen (username + e-mail), regenerates its password, or revokes it; the generated
  password is shown once and never stored in clear. Deactivating a partner deactivates its login (D1).
- R3. A partner signs in on the same `/login` and lands on its portal (`/parceiro`), a shell of its
  own with Franco's brand, the partner's name, and only the portal's links. Every back-office route
  and endpoint refuses it (403 on the API, a bounce in the SPA) (D2).
- R4. Every portal read and write is scoped twice: the firm (`FirmScope`) and the partner
  (`CurrentPartner`, from the signed-in user). Another partner's referral, or any lead never referred
  to this partner, answers 404. A user without `partner_id` (the operator included) gets 403
  `portal.noPartner` on the portal API (D2).

**Partner portal and ledger**
- R5. Inbox: the partner's referrals, open before closed, newest first, with the KPIs Novos ·
  Em andamento · Fechados · SLA estourado and "Próximas reuniões" (demo `partnerInbox`).
- R6. What a partner sees on a referral: company, sector · regime · porte, the diagnostic items its
  specialty covers (titles only), the score, Franco's message, received date, status and SLA. The
  lead's contact (name, phone, e-mail) appears **only after the partner accepts**; a portfolio
  client referral (R27) shows Franco's phone and mailbox as the way in. Never: R$ ranges, notes,
  the lead's timeline, other referrals, other partners, the client base (D3).
- R7. Status graph on `ReferralStatus`: enviado → aceito | recusado; aceito → contato; contato →
  reuniao (meeting date-time required, in the future); reuniao → proposta; proposta → fechado (first-
  year contract value in R$ required, > 0); fechado and recusado are terminal. A wrong move is 422
  `referral.invalidTransition`. Each move stores who and when (`status_at`; `contacted_at`,
  `meeting_at`, `deal_value_cents`, `closed_at` where they apply) (D4).
- R8. SLA: 24 business hours from the referral to the first contact, counted on Franco's business
  hours (seg–qui 8–18, sex 8–17, `America/Sao_Paulo`) minus Phase 1's national holidays; states ok
  (< 12 h) · atenção (12–24) · estourado (≥ 24) while open; cumprido / fora once contacted;
  encerrado when declined. The demo's chip copy (`slaChip`).
- R9. Every move writes one `encaminhamento.status` line on the lead's timeline (through
  `LeadService`) and Franco's team is notified in the bell on aceito, recusado and fechado. The lead
  stays Franco's: its stage never moves because of a partner.
- R10. Franco's side sees the partner's progress: the lead detail's routing panel and the trail show
  each referral's status, SLA chip and, when closed, the contract value.
- R11. Ledger: the firm has one money model for the whole network — `comissao` (10 % of the
  first-year contract value, per closed referral) or `assinatura` (R$ 490 a month per partner with an
  active login, closings add nothing) — set by `partners.manage` (D10). The partner's "Extrato" shows
  the month's lines and "Total devido à Franco" under the current model; Reinaldo's "Extrato da rede"
  shows every partner's month, the total, and what the other model would give. Every amount is
  labelled "valor a conferir — sem cobrança automática".

**Chat receptionist**
- R12. `/diagnostico` gets a toggle "Formulário · Conversar no chat". The chat is a guided
  conversation over the **same** diagnostic API: the demo's 13 turns in order (path, CNPJ, lookup
  confirm, sector, regime, company, name, contact, consent, B2B share, band, ICMS incentives,
  export), then the same result, the same slot picker and the same confirmation (D11).
- R13. The chat's replies are quick-reply buttons; typed text is accepted at every turn — free text
  where the turn asks for it (CNPJ, company, name, contact), and elsewhere matched to an option
  (accents and case ignored, "sim/não", a sector or regime name, a number for the B2B share). Text
  that matches nothing gets "Não entendi. Escolha uma das opções abaixo." and the turn stays. It
  never invents an answer about the Reforma (D11).
- R14. A lead started in the chat carries `channel = chat`, and its consent `via = chat`; the
  channel never changes after the lead exists. Switching between form and chat mid-way keeps every
  answer. The pipeline card and the lead detail show the channel; the funnel adds a table "Por
  canal" (formulário · chat) from "início diagnóstico" on.

**Client data: NF-e import**
- R15. NF-e XMLs (model 55, `nfeProc` or bare `NFe`) are imported by upload — one XML or a ZIP —
  and by `php artisan nfe:import <folder> --firm=franco`, both through one `NfeImporter`. Each
  document is matched to a portfolio client: emitted by the client (`saida`) or addressed to it
  (`entrada`); a document matching no client is refused `client_unknown` (D5, D6).
- R16. Per document the importer stores the access key (44 digits, check digit verified, unique per
  firm — a repeat is skipped as `duplicate`), number, series, issue date, emitter CNPJ and CRT,
  recipient CNPJ (or "consumidor" for a CPF or none), total, item count, and per-item checks: IBS/CBS
  group present, CST and cClassTrib filled, 2026 test values shown (CBS 0,9 % · IBS 0,1 %,
  `facts.md` §2). The raw file is kept in private storage, never served publicly (D7).
- R17. Each import is a batch with who, when, files read, documents stored, and every refusal with
  its file and code (`not_xml`, `not_nfe`, `invalid_key`, `client_unknown`, `duplicate`,
  `too_large`). A broken file never stops the others. Parsing never resolves external entities.

**NF-e readiness**
- R18. "Prontidão da NF-e" lists the Presumido and Real clients with invoices issued in the last 30
  days: invoices, % complete (every item has the group with CST and cClassTrib), invoices at risk of
  rejection 1115 (an item without the group), the emitting system (the most frequent `ide/verProc`,
  standing for the demo's ERP column, filterable); status crítico if < 80 % or any at risk,
  else ok at ≥ 95 %, else atenção (demo `nfeStatus`, thresholds 95/80). Simples clients are out
  until 2027 (`facts.md` §2). Clients with no imported sales show "sem notas importadas".
- R19. Hero, KPIs and filters as the demo (total at risk, received-weighted % complete, críticos,
  prontos; filter by status and by system). A client's detail lists the issue lines with counts and up to five
  sample invoice numbers each (missing group → risk 1115; CST/cClassTrib blank; 2026 test values not
  shown).
- R20. "Avisar cliente" opens the demo's e-mail text for that client (counts filled in), with
  "Copiar texto" and "Registrar aviso"; registering writes a `nfe.aviso` line on the client's
  timeline with who and when. Nothing is sent (clients have no e-mail on file) (D12).

**Supplier credit**
- R21. "Crédito de fornecedores" lists the Presumido and Real clients with purchase invoices in the
  last 90 days: monthly purchases, the share bought from Simples/MEI suppliers (emitter CRT 1, 2 or 4)
  against regime normal (CRT 3), and the credit lost per year from 2027 as a range:
  purchases/month × 12 × Simples share × (26,5 % − 5 %) to × (26,5 % − 3 %) — the reference rate and
  the DAS share are estimates (`facts.md` §2, "Could not read") (D8).
- R22. Sorted by the top of the range; a sector filter; KPIs (total lost per year, clients buying
  from Simples "N de M", mean Simples share, revisions proposed). "Propor revisão" (disabled with
  "Sem fornecedor do Simples" at 0 %) writes `fornecedores.revisao` on the client's timeline and the
  row shows "Revisão proposta".

**Split-payment alert**
- R23. For each client, cash held back per month by split payment = B2B sales per month × 26,5 % ×
  40–60 % (the share settled through split), as a range. B2B sales come from the client's issued
  invoices to a CNPJ in the last 90 days ÷ 3 ("das notas"); with no XML, from the band's revenue ×
  the declared B2B share ÷ 12 ("estimativa do cadastro"). Simples clients are included (D9).

**Radar with XML evidence**
- R24. "Radar da carteira" (Reinaldo) runs `RulesEngine` over every portfolio client: clients
  analysed, with ≥ 1 opportunity, the opportunities' total range (risks never added to the money),
  risk types found; one row per rule type, opportunities first, with its clients, range, rule text
  (demo `radarSummary`).
- R25. Evidence: when a client has imported XMLs, the profile uses the B2B share measured from its
  sales (recipients with a CNPJ, by value) instead of the declared one, and each item tied to the
  XMLs shows its proof — `erp-nfe`: readiness counts; `simples-regular` / `simples-credito` /
  `simples-b2c`: measured B2B share and document count; `split-payment`: the R23 held range. An item
  without XML evidence says "do cadastro". A row opens its clients with the evidence and up to three
  access keys per client (D9).
- R26. The split-payment row shows the Σ of R23's held ranges and the line "retidos na liquidação
  das vendas B2B (estimativa)".
- R27. "Encaminhar os N maiores": per row, the partner picker offers only partners whose specialty
  covers the rule; the batch is the 10 largest clients not yet referred to that partner and with
  consent; the row says "N sem autorização ficam fora do lote". Each client referral goes through a
  portfolio lead (`channel = carteira`, stage cliente, the client's id), so it reaches the partner's
  inbox and the trail like any referral, with the client's consent copied on it (D13).
- R28. Client consent: a client carries `partner_consent` (sim · não · empty) with when and
  `via = contrato`; the CSV import gains an optional 8th column `autoriza_parceiros`; empty means no
  consent. The local seed gives ~85 % consent (demo).

**Seeds (local only)**
- R29. Local seeds extend Phase 1's: one login per seeded partner, 12 referrals in the demo's
  status plan (SLA ok · atenção · estourado among the new ones, 4 closed with values), a few chat
  leads, XMLs for 40 Presumido/Real clients (90 days, sales and purchases) generated with invented
  CNPJs and CPFs, client consent on ~85 %. Every seeder refuses production.

## Acceptance criteria
Layers: `→ Pest`, `→ Vitest`, `→ E2E` (Playwright, API mocked), `→ Chrome` (real API + seeded DB).
- A1 (R1, R2): `partners.manage` creates a partner access → a `parceiro` user with that `partner_id`,
  the password shown once, a second active login for the same partner refused (409); regenerate
  changes it; revoke and partner deactivation make the next login answer `auth.accountInactive`; an
  `equipe` user gets 403 on the access endpoints. → Pest
- A2 (R3, R4) **permission matrix**: a dataset over every route in `routes/api.local.php` — a
  `parceiro` user gets 403 on each non-portal, non-public route; `equipe`, `ceo` and `operador`
  without a `partner_id` get 403 on each portal route; a guest gets 401 on both. A test fails when a
  new route is added without being classified. → Pest
- A3 (R4) **isolation**: partner A never lists, reads or moves partner B's referral (404), nor one of
  firm B's partners with the same id shape (404); a lead never referred to A is not reachable by any
  portal route. → Pest
- A4 (R3): a partner signs in and lands on `/parceiro`; its rail has only the portal; `/painel` and
  every back-office route bounce it to the portal; staff never see the portal links. → Vitest + E2E
- A5 (R6): before accept, the referral answer has no contact name, phone or e-mail and no R$ field;
  after accept it has the contact; a portfolio referral shows Franco's contact; no answer of the
  portal ever carries `range`, notes or another referral. → Pest
- A6 (R7): every allowed move in the graph succeeds and every other is 422
  `referral.invalidTransition`; reunião without a future date and fechado without a value > 0 are
  422; terminal states accept nothing. → Pest (unit on the enum + feature)
- A7 (R8): with `Carbon::setTestNow`, a referral sent Friday 16:00 is at 1 h on Monday 8:00 (weekend
  skipped), atenção at 12 h, estourado at 24 h; a holiday counts 0; contact at 20 h → cumprido, at
  30 h → fora; declined → encerrado. → Pest (unit)
- A8 (R9, R10): accept, decline and close each add one `encaminhamento.status` line and one bell
  notification to every active equipe/ceo member; the lead's stage is unchanged; the routing panel
  and the trail show the new status and SLA. → Pest + Vitest
- A9 (R11): with 2 closed referrals of R$ 12.000 and R$ 24.000 in the month, comissão gives R$ 1.200 +
  R$ 2.400 = R$ 3.600 and assinatura gives R$ 490; switching the model changes both screens; a
  partner's extrato never shows another partner's line; an `equipe` user gets 403 on the network
  ledger and on the model switch. → Pest + Vitest
- A10 (R12, R14): answering the chat with the profile "Simples, B2B 80 %, serviços" produces a lead
  with `channel = chat`, consent `via = chat`, and the same result count as the form with the same
  profile; switching to the form mid-way keeps the answers. → Vitest + E2E
- A11 (R13): at the sector turn "saude" selects Saúde, "não sei de nada" re-asks with "Não
  entendi…"; an invalid contact gets the demo's error and stays on the turn. → Vitest
- A12 (R14): the funnel's "Por canal" table counts a chat walk under chat and not under formulário;
  the card shows the channel chip. → Pest + Vitest
- A13 (R15, R16): a fixture `nfeProc` of a client's sale stores the key, CRT, recipient and the item
  checks; a purchase addressed to the client is `entrada`; an XML of no client is `client_unknown`;
  the same file twice is one document and one `duplicate`; a bad check digit is `invalid_key`. → Pest
- A14 (R17): a ZIP with one good XML, one non-XML and one XML carrying an external entity stores
  one document, reports two refusals by file, and never reads the entity's target; the command
  imports a folder the same way. → Pest
- A15 (R16): the raw XML is not reachable by any URL; another firm's documents are absent. → Pest
- A16 (R18, R19): fixtures at 96 % with 0 at risk → ok; 90 % → atenção; 96 % with 1 at risk →
  crítico; 70 % → crítico; a Simples client is absent; issue counts and samples match the fixture
  items. → Pest
- A17 (R20, R22): "Registrar aviso" and "Propor revisão" each write one client timeline line with the
  user; "Propor revisão" at 0 % Simples is refused (422) and its button disabled. → Pest + Vitest
- A18 (R21): purchases of R$ 100.000/month at 30 % Simples give R$ 77.400–84.600 a year
  (100.000 × 12 × 0,30 × 0,215 / 0,235); 0 % gives 0–0. → Pest (unit)
- A19 (R23, R26): B2B sales of R$ 300.000 over 90 days from XML give R$ 10.600–15.900 a month
  (100.000 × 0,265 × 0,4 / 0,6), marked "das notas"; with no XML the cadastro estimate is used and
  marked; the Radar's split row is the Σ. → Pest (unit + feature)
- A20 (R24, R25): the Radar analyses every client; total = Σ of opportunity ranges only; a client
  whose XMLs show 80 % B2B gets `simples-regular` though its declared share is 20 %, with the
  evidence count and access keys; an item without XML says "do cadastro". → Pest
- A21 (R27, R28): a batch sends at most 10, skips clients without consent and those already
  referred to that partner, offers only covering partners, creates `carteira` leads whose referral
  carries the client's consent `via = contrato`, and the partner sees them in its inbox; a second
  identical batch sends 0 and the button says "Todos já encaminhados". → Pest + Vitest
- A22 (R28): a CSV with `autoriza_parceiros` `sim`/`não`/empty imports consent true/false/none; a
  value outside those names the line; the 7-column CSV still imports. → Pest
- A23 (R29, all): the seeds run on a fresh database; every new screen renders with the seed without a
  console error and with no horizontal scroll at 390 and 1440 px; no "Willy" or "APW" anywhere;
  the partner walk login → accept → contato → reunião → proposta → fechado → extrato and Franco's
  view of it pass. → Chrome
- A24 (constraints): no test reaches the network (`Http::preventStrayRequests()` in every Pest file
  touching HTTP; no SEFAZ, LLM or bank client exists in the code); mail stays `log`. → Pest

## Decisions (taken by Claude on 07/11/2026, owner away; each says how to reverse)
- D1 Partner login → a `users` row with `partner_id` and role `parceiro`, created by
  `partners.manage` with a generated password shown once (no invite e-mail: works locally without
  mail and needs no set-password flow; the bootstrap's `username` login is reused). One active login
  per partner. Rejected: a separate `partner_users` guard (a second auth stack for 8 people); magic
  links (need real mail). Reverse: add an invite Mailable later; the column stays.
- D2 Portal scope → one SPA, a third layout for the portal, permission `portal.access` on every
  portal route plus a `CurrentPartner` binding set by middleware (like `CurrentFirm`); portal
  queries filter by it in the repository, never by an id from the client. The permission matrix
  (A2) is the guard against a staff endpoint leaking. Reverse: none needed.
- D3 What a partner sees → the referral's profile and covered items always; the contact only
  after accepting (it has agreed to call within the SLA; a declined referral never saw a phone).
  Rejected: contact from the start (the demo) — a partner that declines should not keep the data.
  Reverse: drop the accept condition in `PortalReferralResource`.
- D4 Status → the demo's graph exactly, on the existing `ReferralStatus` enum
  (`allowedTransitions`, `canTransitionTo`, `isTerminal`, per `arcus-api-architecture`), no going
  back; Franco cannot move a partner's status (it reads it). Reverse: widen the graph.
- D5 Where XMLs come from → upload (XML or ZIP) and an artisan folder import, behind a
  `NfeDocumentSource` contract with the local `FolderNfeSource`/upload drivers only. SEFAZ DF-e
  distribution needs each client's A1 certificate and a live endpoint: impossible locally and
  outward, so no driver is written (Known Gap). Reverse: bind a SEFAZ driver to the contract.
- D6 Matching → by CNPJ against the firm's portfolio (emitter → saída, recipient → entrada); a
  document of no client is refused, not parked. Reverse: an "unmatched" bucket.
- D7 Storage → parsed facts in `nfe_documents` (one row per document, item checks as counts plus
  the sample numbers), the raw XML in `storage/app/private/nfe/<firm>/<client>/<key>.xml`. No item
  table: the screens need counts, not lines. The signature is not verified (Known Gap: an altered
  file is believed). Reverse: an `nfe_items` table.
- D8 Supplier regime → CRT 1/2/4 = Simples/MEI, 3 = regime normal: the XML cannot tell Presumido
  from Real, so the demo's three-way bar becomes two (Simples · regime normal). Lost credit is a
  range on the DAS share 3–5 % (the demo's single 4 %). Reverse: look suppliers up on BrasilAPI.
- D9 Evidence → XML facts override the declared B2B share only when the client has ≥ 10 sales
  documents in the window; the rule set stays `RulesEngine`'s 13 (no new rule types). Reverse:
  `RadarService::MIN_DOCUMENTS`.
- D10 Money model → one per firm (the demo's "a troca vale para a rede inteira"), default
  `comissao`, 10 % and R$ 490 stored on the firm, changed by Reinaldo; the partner sees only its own
  total under the current model, never the comparison (that is Franco's negotiation). Nothing is
  billed. Reverse: per-partner model column.
- D11 Chat engine → a deterministic scripted receptionist in the SPA (a `ReceptionistScript` with
  a typed-text matcher), no LLM: the demo is scripted, a model would need a key and an outward
  call, and the turns map 1:1 to the form's steps. An LLM driver is out of scope (Known Gap).
  Reverse: a server `ReceptionistBrain` contract with a model driver.
- D12 Client warnings → "Avisar cliente" and "Propor revisão" record lines on a client timeline
  (`client_events`, owned by Portfolio); no e-mail is sent because clients carry no e-mail.
  Reverse: an e-mail column and a Mailable.
- D13 Client referral → through a portfolio lead (the demo's `carteiraLead`) so the referral,
  trail, SLA and portal stay one shape; `leads.client_id` nullable, no cross-module FK (rnc-1 W25).
  Reverse: a `client_id` on `referrals`.
- D14 Who sees what → NF-e readiness and supplier credit: `fiscal.read` (equipe, ceo); XML import:
  `fiscal.import` (ceo); Radar and its batch: `radar.manage` (ceo); network ledger: `ledger.read`
  (ceo); money model and partner access: `partners.manage`; portal: `portal.access` (parceiro).
  `operador` holds every key but no `partner_id`. Reverse: `RoleSeeder` key lists.
- D15 Migrations → the project has not launched: new columns are folded into the `create_*`
  migrations (`users.partner_id`, `referrals` portal columns, `firms` money model, `leads.client_id`,
  `clients` consent), new tables get their own `create_*`; `make fresh-seed` rebuilds
  (`arcus-api-architecture` §3).

## Constraints, met by
- Franco on top, Willy/APW nowhere → R3, A23 (and `noPartnerNetworkNames.spec.ts` extended).
- Franco talks to every lead first; partners only see referred leads with consent → R4, R6, R27, A3, A5, A21.
- Partner portal with strictly scoped access → R1–R4, A1–A4.
- No real third-party calls, deterministic local drivers → D5, D11, A24.
- XMLs private, invented in seeds → R16, R29, A15.
- Figures as ranges, estimates → R11, R21, R23, R24, A18, A19.
- Local only, mail `log`, no push → plan's every phase; A24.

## Screens
- Portal (parceiro): `/parceiro` (inbox, cards with actions and SLA), `/parceiro/extrato`
  (+ `/partner`, `/partner/statement`).
- Reinaldo: `/reinaldo/parceiros` gains "Acesso ao portal"; `/reinaldo/extrato` (network ledger +
  model switch); `/reinaldo/radar`; `/reinaldo/notas` (XML import with batches).
- Equipe: `/equipe/nfe`, `/equipe/fornecedores`; lead detail and trail show referral status + SLA.
- Site: `/diagnostico` with the Formulário · Chat toggle.

## Out of scope
Everything in intent "Out of scope"; the demo's network agenda / joint meetings ("Serviços e
agenda", `network.js`) — not in the module's menu line (`seed.js` 306); partner self-sign-up;
billing commissions.

## Changes while building

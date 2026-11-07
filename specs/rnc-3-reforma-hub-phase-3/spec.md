# Spec: Franco Reforma Hub — Phase 3 (growth)
Status: closed 07/11/2026 (decided by Claude in the owner's absence, every D# reversible; amended by plan.md AM15–AM23) · ID: rnc-3 · Intent: approved 07/11/2026
Proposal: none — the behaviour reference is the approved demo `battonidev-1`
(`/Users/battoni/Work/battoni.dev/dev/demos/franco-reforma-hub/src/` — `views/carteira.js` 279–392
(contracts), `store.js` 10–86 + `views/operator.js` 35–136 (Express/Prime), `views/operator.js`
261–343 (RNC), `views/ceo.js` 94–239 (briefing), `views/fiscal.js` 404–666 (assistant), `engine.js`
424–467 and 547–592, `seed.js` 296–317 (MODULES) and 361–457) and its spec
`/Users/battoni/Work/battoni.dev/dev/specs/battonidev-1-reforma-hub/spec.md` (R22, R28, R32, R34,
R35, C4, C5). Where this spec is silent, do what the demo does; copy its wording, rules and numbers,
never its code style. The demo keeps everything in memory: storage, review, permissions and the
public brand switch are designed here. Builds on `rnc-1` and `rnc-2` (their rules, amendments and
changes all still hold).

**Scope check (W1).** `commercial.md` §1 lists Phase 3 as contract alert, Express/Prime, RNC via
Franco, with the staff assistant "Later"; §3 (06/11, the later decision) prices Phase 3 at 185 h as
contracts 20 · sub-brands 30 · RNC 60 · Reinaldo's briefing 15 · assistant 60, and `seed.js` MODULES
puts all five in phase 3. This spec builds the five. "RNC channel" is a sales channel (Franco brings
other Rede Nacional de Contabilidade firms), not a video channel: no video host exists in scope.

## Requirements

**Express and Prime (sub-brands of Franco)**
- R1. A firm has sub-brands (`firm_brands`, Firm module): slug, name, short name, tagline, primary
  and accent colours, four proof points (value + label), active. Franco's own look stays on `firms`
  as the main brand. The seed gives `express` "Franco Express Contabilidade" and `prime` "Franco
  Prime Contabilidade" with the demo's colours, taglines and proof points (D1).
- R2. `GET public/firm` with header `X-Brand: <slug>` answers the brand's name, short name, tagline,
  colours and proof points over the firm's own address, phone, mailbox and hours; an unknown or
  inactive slug is 404 `brands.notFound` (on `POST public/events` it falls back to the main brand); no header is the main brand. The SPA reads `?marca=<slug>`
  once, keeps it for the tab (`sessionStorage`), sends `X-Brand` on every public call, and
  `?marca=franco` clears it, and so does any 404 `brands.notFound` (D2).
- R3. Under a brand everything the visitor sees re-themes: page titles, header, hero tagline, proof
  points, footer, colours (runtime CSS custom properties from primary/accent), the chat bubbles'
  name, the consent sentence ("…parceiros da Franco Express Contabilidade"), the confirmation
  e-mail's sender name and signature. Address, phone and mailbox stay Franco's; no other firm's name
  appears; the back office stays Franco's (demo C4).
- R4. A lead started under a brand carries `brand_id` (null = main), fixed at creation like the
  channel. The pipeline card and the lead detail show a brand chip; the pipeline gets a filter
  "Marca" (Todas · Franco · Express · Prime). Team, agenda, partners and routing are shared: one firm.
- R5. "Marcas" console (`/operador/marcas`, `brands.manage`): Franco's row ("Marca principal") and
  the sub-brands with their lead count; edit name, short name, tagline, colours and proof points
  (hex `#rrggbb`; white text on the primary must reach 4,5:1, else 422 `brand.lowContrast`);
  activate/deactivate; "Ver como <short>" (disabled "Inativa" for an inactive brand) opens `/?marca=<slug>&previa=1` in a new tab, where a
  banner says "Prévia: o site como a <name> aparece ao visitante." (D3, D4).

**RNC channel**
- R6. Channel firms (`channel_firms`, new Channel module, scoped to the channel firm Franco): name,
  city, stage contato → demo → implantacao → ativo, `stage_at`, monthly fee (default R$ 3.500), share
  (default 15 %), notes. No people's data, no login, no tenant is created (D5).
- R7. `channel.manage` (operador) adds a firm, edits its fee (R$ 0,01–100.000,00), share (0–50 %) and notes, and advances it one
  stage ("Avançar para <próxima etapa>"); any other move is 422 `channel.invalidTransition`, ativo is
  terminal. Each move writes a `channel_firm_events` row (who, from, to, at UTC).
- R8. Screen "Canal RNC" (`/operador/canal-rnc`): hero "Receita da Franco pelo canal" = Σ of the
  active firms' shares per month, "· R$ X por ano", "Mais R$ Y/mês quando os outros ativarem"; the
  count per stage; one row per firm with the stepper, "R$ fee/mês", the share or "na ativação", and
  the advance button or the chip "Ativo". Share = `intdiv(fee_cents × share_bp + 5000, 10000)`
  (half-up). Every amount says "valor a conferir — sem cobrança automática" (D6).
- R9. Reinaldo (`channel.read`) sees the screen read-only at `/reinaldo/canal-rnc`: no add, no
  edit, no advance, and no platform price — only his share per firm (R$ 525/mês), the stage and the
  year total, with no "R$ 3.500" and no "pela plataforma" lede (the API omits `monthly_fee_cents` and
  `share_bp` without `channel.manage`; `commercial.md:41`, the platform path is undecided). Neither screen says anything about how the platform's owners split the rest.

**Reforma assistant**
- R10. Knowledge base (`assistant_notes`, new Assistant module): question, answer, rule (the legal
  citation), source (label + URL, from `facts.md`), `as_of` (the date the source was read),
  strong and weak keywords, status `rascunho · revisada · aposentada`, reviewer, `reviewed_at`,
  `last_edited_by`. Written at "Base da Reforma" (`/equipe/base-da-reforma`) by `assistant.manage`
  (ceo) or by a specialist (rail entry for either); retiring a note and the gaps list are
  `assistant.manage` only; editing a reviewed note's question, answer, rule, source or keywords
  returns it to rascunho (D7).
- R11. Only a user holding `assistant.ask` with `is_specialist`, and not the note's last editor, marks
  a note revisada (403 `assistant.notReviewer`). A review is served while `reviewed_at` is at most 90 days old (day 90 still served); after that the note shows
  "revisão vencida" and is not served until reviewed again (D8). "Base da Reforma" counts "N notas vencem em 7 dias" (reviewed 83–90 days ago) and the reviewer's bell rings at day 83.
- R12. Ask ("Assistente da Reforma", `/equipe/assistente`, `assistant.ask`: equipe, ceo):
  `POST assistant/ask` goes to the `ReformaAnswerer` contract, whose bound driver `curated` returns
  the best served note of the firm (reviewed and in date) by the demo matcher — lower-case, accents
  and non-alphanumerics stripped; a key of ≥ 4 letters matches a token starting with it, a shorter
  key the whole token; score 2 × strong + 1 × weak; at least 2; ties by the newest `reviewed_at`,
  then the lower id. A question is 3–300 characters. The answer shows the text, "Regra: <rule>", "Fonte: <source>, lida em <as_of>" and
  "revisado por <name> em <date>". The suggestion chips ask by note id (D9).
- R13. No served note → the demo's sentence "Não há nota revisada pela equipe para essa pergunta,
  então não vou arriscar uma resposta." and "Encaminhar ao especialista" with a picker of the firm's
  active specialists (default the first by name). It creates an `assistant_questions` row (question,
  asker, specialist, status aberta) and a bell notification to that specialist. Nothing is ever
  generated.
- R14. "Perguntas para especialista" (`/equipe/assistente/perguntas`): a specialist sees the
  questions assigned to them and answers in text (status respondida, the asker's bell rings); "Virar
  nota" opens a rascunho prefilled with the question and the answer. `assistant.manage` sees every
  question of the firm.
- R15. Every ask is logged (`assistant_asks`: user, question, note or none, at). "Base da Reforma"
  shows "Perguntas sem nota" of the last 30 days, grouped by the normalised text, most asked first.
- R16. The driver is chosen by `config('assistant.driver')`, whose only value is `curated`; an
  unknown driver fails at boot. No model, embedding or other outward client exists in the code
  (Known Gap: an answer "por significado") (D9).

**Contract repricing alert**
- R17. Client contracts (`client_contracts`, Portfolio module): client, kind (fornecimento ·
  prestacao_servicos · locacao · distribuicao · manutencao), annual value, start year, end year,
  `has_reforma_clause`; firm-scoped, never public (D10).
- R18. Contracts come by CSV at "Alerta de contratos" (`/reinaldo/contratos`, `contracts.manage`):
  `cnpj_cliente;tipo;valor_anual;inicio;fim;clausula_reforma`, read as the clients CSV is (labels or
  slugs, `1.200.000,00`, `;` or `,`, `sim`/`não` case- and accent-insensitive). A CNPJ that is no
  client, a year outside 2000–2100 or `fim` before `inicio` names its line; the same client + kind +
  start + end updates the row; an empty clause is "não" on a new row, and on an update it keeps what is stored — a contract marked through "Cláusula incluída" is never downgraded by a re-import; good lines are stored and bad lines
  reported (a bad line never stops the others). The local seed gives the demo's 40 contracts.
- R19. The alert lists the contracts ending in 2027 or later without the clause, ranked by exposure
  = annual value × years exposed (`min(end, 2033) − max(start, 2027) + 1`, floor 0), highest first,
  ties by id. KPIs "Sem cláusula da Reforma N de M" (at risk of all contracts), "Valor anual dos
  contratos" and "Valor × anos expostos" (Σ over the at-risk ones), "Encaminhados ao jurídico"
  (at-risk contracts with a referral). Each row: client, "<tipo> · desde <início> · <valor>/ano · até <fim>", the '27–'33
  chips lit to the end year, the exposure bar. The note carries the dated statement of `facts.md` §2
  ("Everyone": long contracts across 2027–2033 need a price clause). Empty: "Nenhum contrato
  atravessa a transição sem cláusula."
- R20. "Encaminhar ao jurídico": a picker of the active partners with specialty
  `advocacia_tributaria` first, then `precificacao_controladoria` ("Sem parceiro jurídico na rede."
  when none). The client's partner consent (rnc-2 R28) is required: without it the button is
  disabled with the demo's reason and the API answers 422 `consent_required`. The referral goes
  through the client's portfolio lead (rnc-2 D13), carries `contract_id` and the demo's message, is
  one per contract and partner (409 `already_referred`), and the row shows "Com <parceiro>" (D11).
- R21. In the partner portal a contract referral shows the contract summary (kind, end year,
  "sem cláusula da Reforma" and a value range by fixed bands, never the exact annual value) and Franco's contact as the way in; never the client's other
  contracts, its fee or its other referrals.
- R22. "Cláusula incluída" marks the contract `has_reforma_clause` (a `contrato.clausula` line on the
  client timeline with who and when) and it leaves the list.

**Reinaldo's briefing**
- R23. `GET briefing` (`briefing.read`: ceo, operador) answers one payload: new leads in 7 days (and
  in 24 h), meetings in the next 5 business days (and today), referrals waiting for a first contact
  by SLA state (ok · atenção · estourado, rnc-2 R8), the open pipeline (lead · reuniao_marcada ·
  proposta) with its count and annual fee range. Portfolio (`carteira`) leads are left out of the lead,
  meeting and pipeline figures; the referral tile counts every open referral, contract and Radar
  ones included (a partner still owes the first contact).
- R24. Pipeline value: each open lead's band gets the portfolio's monthly fee quartiles (p25–p75) of
  the clients in that band × 12, summed into a range; a band with fewer than 5 clients uses every
  client's quartiles; a lead without a band uses every client's. Quartiles are nearest-rank on the
  fees in cents (p25 = the ⌈0,25 n⌉-th); an empty portfolio gives no range ("sem base na carteira"). Label "honorário anual estimado
  (faixa da carteira)" (D12).
- R25. "As 3 ações de hoje": the first three that apply, in the demo's order and copy — breached SLA
  ("Cobrar <parceiro>: <empresa> está há <h>h úteis sem primeiro contato (meta 24h)." → the
  partner's trail), meetings today (→ agenda), open proposals with their range (→ pipeline at
  proposta), partial leads (→ pipeline at lead_parcial), the Radar's first opportunity type
  (→ `/reinaldo/radar`).
- R26. Screen "Briefing do dia" (`/reinaldo`, `/reinaldo/briefing`, `/ceo/briefing`): greeting by
  São Paulo hour ("Bom dia · Boa tarde · Boa noite, <first name>"), the four KPIs, the actions with
  their buttons, the countdown to 01/01/2027 00:00 São Paulo ("CBS em vigor em"; from that instant "CBS em vigor desde 01/01/2027"), with the dated
  caption of `facts.md` §2 and its note that entities asked to postpone CBS to 2028), "Próximas
  reuniões" (5), "Indicações e SLA de 24h úteis" (5, "e mais N"), "Pipeline por etapa". `/reinaldo`
  lands here instead of Métricas.
- R27. `briefing:send`, scheduled at 07:00 São Paulo on business days (Phase 1's holidays), mails
  the same briefing to every active `ceo` of each firm, with a link to `/reinaldo`; `log` mailer
  locally (D13).

**Access and seeds**
- R28. New keys: `brands.manage`, `channel.manage`, `channel.read`, `assistant.ask`,
  `assistant.manage`, `contracts.manage`, `briefing.read`. Grants: ceo — `channel.read`,
  `assistant.ask`, `assistant.manage`, `contracts.manage`, `briefing.read`; equipe — `assistant.ask`;
  operador every key; parceiro none. Rail entries by key (D14).
- R29. Local seeds extend Phases 1–2: the two brands, a few leads under each, the 6 demo channel
  firms (3 ativo, "(exemplo)"), the demo's 10 notes (9 revisada between 27 and 31/10/2026 by the
  seeded specialists, `credito-simples` as rascunho because `facts.md` §2 marks it unverified), two
  open questions, the 40 contracts (about 5 of the at-risk ones on clients without consent). Every
  seeder refuses production.

## Acceptance criteria
Layers: `→ Pest`, `→ Vitest`, `→ E2E` (Playwright, API mocked), `→ Chrome` (real API + seeded DB).
- A1 (R1, R2): `GET public/firm` with `X-Brand: express` answers "Franco Express Contabilidade", its
  colours and proof points with Franco's phone and mailbox; an unknown, an inactive and another
  firm's brand slug are each 404 `brands.notFound`; no header answers the main brand. → Pest
- A2 (R2, R3): under `?marca=express` the public layout sets the brand's CSS properties, title,
  tagline and footer, the consent sentence names the brand, and no other firm's name renders; a
  reload keeps the brand, `?marca=franco` drops it. → Vitest + E2E
- A3 (R3, R4): a diagnostic started with `X-Brand: prime` creates a lead with Prime's `brand_id`; a
  later PATCH with another or no header never changes it; the stored consent text names Prime; the
  confirmation e-mail (`Mail::fake`) is from "Franco Prime Contabilidade" at Franco's mailbox. → Pest
- A4 (R4): `GET leads?brand=express` returns only Express leads, `brand=main` only the main ones, an
  unknown slug 422; the card and the lead detail show the chip. → Pest + Vitest
- A5 (R5): `brands.manage` updates a brand; a bad hex is 422; a primary of `#ffff00` is 422
  `brand.lowContrast`; ceo, equipe and parceiro get 403; firm B's brand is 404. → Pest
- A6 (R5): "Ver como Express" opens `/?marca=express&previa=1` in a new tab and the site shows the
  preview banner; without `previa` there is no banner. → Vitest + E2E
- A7 (R8): 3 active firms at R$ 3.500 and 15 % give R$ 525 each, R$ 1.575/mês and R$ 18.900/ano, and
  3 inactive add "Mais R$ 1.575/mês"; R$ 3.500,10 at 15 % gives R$ 525,02 (half-up — truncation would
  give 525,01). → Pest (unit + feature)
- A8 (R6, R7): each forward move succeeds and writes one event; skipping, going back and moving an
  ativo firm are 422 `channel.invalidTransition`; a firm of firm B is 404; ceo gets 403 on every
  write and 200 on the read, whose resource carries no `monthly_fee_cents`/`share_bp` (the operator's does); equipe 403 on both. → Pest
- A9 (R9): Reinaldo's screen renders his share, the stage and the year total without any add, edit or advance control and without "R$ 3.500" or "pela plataforma"; the
  operator's has them; neither renders "Willy", "APW", "Battoni" or any split of the platform's
  share. → Vitest
- A10 (R10, R11): editing a reviewed note's answer returns it to rascunho; a non-specialist and the
  last editor are 403 `assistant.notReviewer`; another specialist reviews it; with
  `Carbon::setTestNow` exactly 90 days after `reviewed_at` the note is still served and 91 days after it is not served and lists as "revisão
  vencida". → Pest
- A11 (R12): the matcher — "quando começa a cobrança da CBS?" finds `cbs`; "cobr" matches
  "cobrança"; a 3-letter key matches only the whole token; a score of 1 finds nothing; a tie goes to
  the newer review. → Pest (unit)
- A12 (R12, R13): `ask` answers a served note with rule, source, `as_of`, reviewer name and date;
  rascunho, aposentada, expired and firm B's notes never answer; an unmatched question answers
  `note: null` and the fixed sentence only. → Pest
- A13 (R13, R14): the hand-off creates one question and one bell notification for the picked
  specialist; a non-specialist target is 422; the specialist's answer rings the asker's bell; another
  specialist answering it is 404; "Virar nota" creates a rascunho with the question. → Pest
- A14 (R15; R12–R15 UI): `GET assistant/gaps` groups "Quando começa a CBS?" and "quando comeca a
  cbs" as one line with 2, leaves out asks older than 30 days and asks that found a note (Pest); the
  answer card shows "revisado por … em …" and the source line; an unmatched
  question shows the hand-off; the inbox answers; "Perguntas sem nota" renders the grouped lines.
  → Pest + Vitest + E2E
- A15 (R16): an unknown `assistant.driver` fails at boot; no file under `app/Modules/Assistant` uses
  `Http::`, Guzzle or a socket (a test greps the module). → Pest
- A16 (R17, R18): a CSV with labels, slugs, `1.200.000,00`, `SIM`/`nao`/`Não` imports; an unknown
  CNPJ, a year 1999 and `fim < inicio` name their lines; the same key twice updates one row; a contract marked through `clause` and re-imported with an empty clause stays marked. → Pest
- A17 (R19): fixtures — a contract ending 2026 and one with the clause are absent; one ending 2035
  counts 7 years; 2028–2030 counts 3 and 2030–2033 counts 4; the order is by exposure then id; the KPIs match by hand. → Pest
- A18 (R20): `GET contracts/legal-partners` lists the active advocacia partners before the
  precificação ones and is empty without them; a partner of another specialty is 422 `partner_not_legal`; a client without consent is
  422 `consent_required`; a second send to the same partner is 409; the referral sits on the
  client's portfolio lead with `contract_id` and the demo message; a Radar referral of the same client
  to the same partner does not block it, nor it the Radar one. → Pest
- A19 (R21): the partner sees the contract summary with a value range (no exact annual value) and Franco's contact, no fee, no other contract;
  partner B gets 404. → Pest
- A20 (R22; R19–R22 UI): `POST contracts/{id}/clause` sets the clause, writes one `contrato.clausula`
  line with the user and drops the row from `GET contracts` (Pest); a row without consent has the
  button disabled with the reason; sending shows "Com <parceiro>"; with no legal partner the row says
  "Sem parceiro jurídico na rede."; "Cláusula incluída" removes the row. → Pest + Vitest + E2E
- A21 (R23, R24): with fixtures the briefing counts 7-day and 24-hour leads, 5-business-day and
  today's meetings, the SLA states and the open pipeline; a `carteira` lead counts nowhere; the range
  is Σ of band quartiles × 12, falling back for a band of fewer than 5 clients. → Pest
- A22 (R25): the action list keeps the demo's order, stops at three, and leaves out what does not
  apply (no breached SLA → it starts at meetings). → Pest (unit)
- A23 (R26): the screen renders the greeting by hour, the KPIs, the actions with their links and
  the countdown to 01/01/2027 under a fixed clock; `/reinaldo` lands on it. → Vitest
- A24 (R27): the schedule holds `briefing:send` at `0 7 * * *` in `America/Sao_Paulo`; on a business day the command queues one mail per active ceo per firm, none on
  a Saturday or a holiday, none to an inactive ceo; mail stays `log`. → Pest
- A25 (R28) **matrix and isolation**: every new route is classified (`staff` + its key) or the
  matrix fails; parceiro is 403 on each; equipe is 403 on brands, channel, contracts, briefing and
  `assistant.manage` routes; firm B's brands, channel firms, notes, questions and contracts are 404.
  → Pest
- A26 (R29, all): the seeds run on a fresh database; every new screen renders with the seed at 390
  and 1440 px without a console error or sideways scroll; no "Willy" or "APW" anywhere; the walks
  — Express site to a lead, RNC advance, ask → hand-off → answer, contract → partner's portal, the
  briefing — pass. → Chrome
- A27 (constraints): `tests/Pest.php` calls `Http::preventStrayRequests()` for every test (a test asserts the line exists); no LLM,
  embedding or video client exists; mail stays `log`. → Pest
- A28 (R11): with `setTestNow`, a reviewed note 82 days old rings no bell, 83 days old rings one bell to its reviewer (not repeated by a second run), and the counter "notas vencem em 7 dias" counts the notes 83–90 days old. → Pest

## Decisions (taken by Claude on 07/11/2026, owner away; each says how to reverse)
- D1 Express/Prime → brands of the one Franco firm (`firm_brands`), not firms: the demo's C4 says
  they share Franco's data, network, address and mailbox (`operator.js:5,131`: "mesmos dados, mesma rede, mesmo endereço"); `commercial.md:13` speaks of "tenants", and sub-brands of one firm is still the decision because the sub-brands have no tenant of their own to manage, and a second firm would split the
  pipeline and need the operator's cross-firm access (Firm `RULES.md`: "Phase 3", still not built).
  Rejected: two `firms` rows with a parent. Reverse: a `parent_firm_id` and a data copy.
- D2 Brand on the public site → `?marca=` kept per tab and an `X-Brand` header, because locally
  there is one host (`localhost`, rnc-1 AM1). Host mapping (`express.franco…`) is a Known Gap for
  hosting. Rejected: a path prefix (`/express/...`), which doubles every public route. Reverse: a
  `host` column on `firm_brands` read by `ResolveCurrentBrand`.
- D3 Who edits brands → `brands.manage`, operador only (the demo's console is the operator's), while
  the offer of each sub-brand is still an assumption until Franco tells it (`facts.md` §6); Reinaldo
  sees the brands' leads in the pipeline. Reverse: grant `brands.manage` to ceo.
- D4 Brand colours → validated contrast (white on primary ≥ 4,5:1, WCAG AA), because a re-theme can
  break the site's legibility. Reverse: drop the rule in `UpdateBrandRequest`.
- D5 RNC firms → records of Franco's channel pipeline, not tenants: no login, no provisioning on
  ativo, no people's data. Onboarding a second firm is outside Phase 3 (Known Gap). Rejected: a
  `firms` row per RNC firm (an empty tenant nobody runs). Reverse: provision a firm on ativo.
- D6 RNC money → fee and share stored per firm (defaults from `config/channel.php`: 350000 cents,
  1500 bp), half-up share, forward-only stages as the demo; nothing is billed. Reverse: config.
- D7 Assistant knowledge → curated notes in the database with source and date, written by the ceo or
  a specialist, served only when reviewed (commercial §1 "only as search over Franco's own reviewed
  notes, a named reviewer"). Reverse: none needed.
- D8 Review → four eyes (a specialist who is not the last editor) and 90 days of validity, so every
  served statement is dated and was defended by a named person recently; the Reforma's dates move
  (`facts.md` §1: CBS postponement request). Reverse: `AssistantNote::VALID_DAYS`, the policy.
- D9 Engine → a `ReformaAnswerer` contract with one deterministic `curated` driver (the demo's
  matcher, server-side). No model driver is written at all — "off by default" is then guaranteed by
  absence, and no key exists (Known Gap). Reverse: add a driver class to the config map.
- D10 Contracts → CSV import + seed, value per year, exposure capped at 2033 (the transition's end;
  the demo's seed never passes it). No PDF/OCR upload (out of scope). Reverse:
  `ContractExposure::LAST_YEAR`; a total-value column.
- D11 Legal referral → through the portfolio lead with `referrals.contract_id` and a
  `subject_key` (`lead` · `contrato:<id>`) in the unique key (`lead_id, partner_id, subject_key`), so a
  contract and a Radar referral of one client to one partner do not collide. Partners by specialty,
  as the demo (not by rule coverage). Reverse: drop `subject_key` from the unique.
- D12 Pipeline in R$ → the portfolio's own fee quartiles by band (a lead has no fee; the demo's
  `fee` is invented), as a range. Reverse: `BriefingService::MIN_BAND_CLIENTS`, a fixed table.
- D13 Daily e-mail → included (the demo's note "chega também por e-mail às 7h"), business days only.
  Reverse: unschedule `briefing:send`.
- D14 Who sees what → as R28; specialists are equipe with `is_specialist`, so the review and the
  inbox check the flag, not a key. Reverse: `RoleSeeder` key lists.
- D15 Migrations and Phase 2 → not launched: new columns fold into their `create_*`
  (`leads.brand_id`, `referrals.contract_id` + `subject_key`), new tables get their own; rnc-2 is
  merged into this branch at the start of each phase, and F6 onward wait for rnc-2 to be finished
  (portfolio leads, client consent, portal, Radar, client timeline). Reverse: none needed.

## Constraints, met by
- Franco on top, Willy/APW nowhere → R3, R5, R9, A2, A26 (brand guard spec extended).
- Franco talks to every lead first; partners see consented referrals only → R4, R20, R21, A18, A19.
- No real personal data in seeds → R6, R29 (firms and notes invented, "(exemplo)").
- Figures as ranges, estimates; RNC amounts "a conferir" → R8, R24, A7, A21.
- Reforma statements dated and defensible → R10–R12, R19, R26, A10, A12.
- Assistant never invents; no model on by default → R13, R16, A12, A15, A27.
- Contracts private, firm-scoped → R17, A25.
- New permissions with matrix and isolation tests → R28, A25 (and A5, A8, A13, A19).
- Local only, mail `log`, no push → R27, A24, A27; every phase.

## Screens
- Site: every public page under `?marca=express|prime` (and the preview banner with `previa=1`).
- Operador: `/operador/marcas` (+ `/operator/brands`), `/operador/canal-rnc` (+ `/operator/rnc`).
- Reinaldo: `/reinaldo` = `/reinaldo/briefing` (+ `/ceo/briefing`), `/reinaldo/contratos`
  (+ `/ceo/contracts`), `/reinaldo/canal-rnc` (+ `/ceo/rnc`).
- Equipe: `/equipe/assistente` (+ `/team/assistant`), `/equipe/assistente/perguntas`
  (+ `/team/assistant/questions`), `/equipe/base-da-reforma` (+ `/team/knowledge`; ceo and specialists); pipeline card, filter and lead detail gain the brand chip.
- Portal: a contract referral's card shows the contract summary.

## Out of scope
Everything in intent "Out of scope"; the demo's Alvorada (another firm's white label) and the
operator's cross-firm console; provisioning an RNC firm as a tenant; any model-backed answer;
contract files; tracking events by brand (the funnel stays per firm).

## Changes while building
- C1 (F1, 07/11, owner away — W25): R2's 404 `brands.notFound` for an unknown or inactive `X-Brand` holds on every public site route (`public/firm`, sectors, diagnostics, booking, report), not only `public/firm`; `POST public/events` alone falls back to the main brand. Reason: the SPA sends `X-Brand` on every public call and clears the stored brand on that 404 (R2), so each call must be able to answer it.
- C2 (F2, 07/11, owner away — W33): R4's board filter reads "Todas as marcas · Marca principal · Franco Express · Franco Prime" — the brands' own short names, from the board's answer — not "Todas · Franco · Express · Prime"; and A6's button reads "Ver como Franco Express" (the demo's `short`). Reason: the short name is what the operator edits in R5, so the filter and the button follow an edit; "Marca principal" is the console's own word for Franco's row.
- C3 (F3a, 07/11, owner away — W40): R7's advance names the stage it moves to (`POST channel/firms/{id}/advance {to}`, the button's "Avançar para <próxima etapa>"); a `to` that is not the next stage is the skip, the step back or the stale click A8 refuses with 422 `channel.invalidTransition`. Reason: with no target the API could not tell a second click from a deliberate move, and A8's skip and back cases had nothing to send.
- C4 (F4, 07/11, owner away — W50): A12's "the fixed sentence only" is the SPA's sentence: `POST assistant/ask` with no served note answers `{question, note: null, specialists}` — the firm's active specialists by name for R13's picker — and no text of its own; F5's screen shows "Não há nota revisada pela equipe para essa pergunta, então não vou arriscar uma resposta." from its locale. Reason: an API that never writes a sentence cannot be made to write a wrong one, and the wording stays the SPA's to translate (`en`).

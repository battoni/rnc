# facts.md — rnc-3 Reforma Hub Phase 3 (copied from rnc-2, §6 added 07/11/2026)

Fetched 2026-11-05. Site pages were curled into the scratchpad. Law and market claims below come from WebFetch or WebSearch of the cited pages; `curl` to planalto.gov.br was blocked.

## 1. Franco Contabilidade today

**Profile**
- Accounting and business-consulting firm, in São Paulo for 35 years. It serves industry, commerce and services, from small to large companies, and abroad (https://franco-rnc.com.br/CEOReinaldo).
- Services page headline (https://franco-rnc.com.br/empresa) says "mais de 34 anos". The CEO page and the landing page say 35 or "mais de 35".
- "More than 500 clients", "approx. 100 employees" (https://franco-rnc.com.br/empresa). The CEO page says "quase uma centena de profissionais".
- CEO: Reinaldo Franco (http://franco-rnc.com.br/CEOReinaldo). His page mentions the tax reform in general terms only.

**Certifications and affiliations**
- ISO 9001:2015.
- PQEC, the Sescon-SP quality programme.
- Member of RNC (Rede Nacional de Contabilidade).
- Associated with Sescon-SP.
- Source: https://franco-rnc.com.br/empresa and https://lp.franco-rnc.com.br/.

**Services** (https://franco-rnc.com.br/servicos)
- Assessoria e Consultoria.
- Contábil.
- Inteligência Fiscal e Tributária. This covers tax benefit studies and recovery of tax credits.
- Departamento Pessoal.
- Legalização Empresarial.
- Assessoria em gestão administrativa e financeira.
- Pessoa Física: IRPF, aposentadoria, empregador doméstico.
- BPO Financeiro.
- Consultoria de RH "Diferente": hunting, cargos e salários, clima, sucessão.
- The page ends with a "Solicitar proposta" CTA.
- The page has a news feed that already covers the reform. On 04/11 it ran "Pendências na Reforma Tributária levam entidades a pedir adiamento da CBS para 2028". This is a signal that dates may shift.

**Contact (public)**
- Av. Corifeu de Azevedo Marques, 2333, 1º e 2º andares, Butantã, São Paulo-SP.
- Phone 11 3732-5500.
- Generic mailbox saf@franco-rnc.com.br.
- Hours: Mon–Thu 08–18, Fri 08–17.
- Source: site footer (https://www.franco-rnc.com.br/).
- The DPO is outsourced (Embrasi). I have not recorded the DPO's personal details.

**Brands and channels**
- Instagram: @francoexpresscontabilidade and @francoprimecontabilidade. These suggest sub-brands Express and Prime.
- LinkedIn: franco-sistemas-empresariais.
- Source: footer, https://www.franco-rnc.com.br/.

**Digital tools already linked** (https://www.franco-rnc.com.br/)
- Ondatta client tools: agenda tributária, certidões negativas, facilitador contábil, formulários and modelos de documentos, all under `https://ondatta.com.br/contabil/…`.
- Solides careers: http://jobs.solides.com/francocontabilidade.
- A "Canal do Titular" (LGPD) page at `/canaldotitular`.
- Link directories: Receita, Sefaz/SP, PGFN, courts, banks.
- A news feed.

**Who built the sites**
- Main site: footer "2026 – Desenvolvido por Ondatta Sites Contábeis" (https://ondatta.com.br). It is a templated platform with bootstrap and `/Content/custom-style-13.min.css`.
- Landing page: footer "V4 Company TellesFreire & Co". Page-builder assets sit on `cdn.greatsoftwares.com.br`, and field names look like a "gpc_campo" form widget.
- Tracking on main site: GA `G-R0HK7BRNLB` and a Meta pixel.
- Tracking on landing page: GTM `GTM-N3X2R59P`.

**Landing page form** (https://lp.franco-rnc.com.br/)
- Headline: "FIQUE LIVRE PARA SER O CHEFE DA SUA EMPRESA!".
- It has no `<select>` elements. All fields are free text, and the first four are required.

| Field | Name | Type | Required |
| --- | --- | --- | --- |
| Nome | `nome` | text | no |
| E-mail | `e-mail` | email | yes |
| Telefone | `telefone` | tel | yes |
| Empresa | `empresa` | text | yes |
| Quantidade de funcionários? | `quantidade-de-funcionirios` | text | yes |
| Quais serviços está buscando | `quais-servios-est-buscando` | textarea | yes |

- There is no regime, sector or revenue question. A diagnostic would add the missing profile data.

**Brand extraction** (from CSS and HTML; treat as indicative, not an official brand book)
- Landing page colors: `#3866f2` (blue, accent), `#101726` (dark navy), `#14c3ff` (cyan, form panel), `#fcfdff` (near-white), `#0e5aaf`, `#003f87` (hover).
- Landing page font: Roboto (Google Fonts, weights 400/700/900).
- Main site colors (`/Content/custom-style-13.min.css`): `#00375a` (dark teal-navy), `#0072c6`, `#14c3ff`, `#10b8d2`.
- Main site text font: not determined. The CSS only declares icon fonts.
- Main site logo URLs:
  - https://franco-rnc.com.br/images/franco-contabilidade.png (also the og:image)
  - https://franco-rnc.com.br/images/franco-contabilidade-negativa.png
  - https://franco-rnc.com.br/images/franco-contabilidade-mono-neg.png
  - https://franco-rnc.com.br/images/reinaldo-franco.png (CEO photo)
  - https://franco-rnc.com.br/images/diferente2.png (RH "Diferente" brand)
- Favicons are at `/favicon/…`.
- The landing page logo is a PNG on cdn.greatsoftwares.com.br/arquivos/paginas_editor/443732-*.png. I could not tell which of the 17 images is the logo.

## 2. Reforma Tributária essentials

**Legal basis**
- EC 132/2023.
- LC 214/2025 (IBS/CBS/IS and the transition). LC 227/2026 and LC 235/2026 amended it. gov.br/fazenda lists topics: cashback, cesta básica, differentiated and specific regimes, transition, and a separate law for the IBS Comitê Gestor.
- Source: https://www.gov.br/fazenda/pt-br/acesso-a-informacao/acoes-e-programas/reforma-tributaria and https://www.planalto.gov.br/ccivil_03/leis/lcp/lcp214.htm.

**New taxes**
- CBS is federal. It replaces PIS and Cofins.
- IBS is state and municipal. It replaces ICMS and ISS. Revenue is shared via the Comitê Gestor (CGIBS).
- IS (Imposto Seletivo) is on goods harmful to health or the environment. It starts in 2027.
- Source: gov.br page above and https://www.barbieriadvogados.com/lc-214/.

**Timeline** (https://www.barbieriadvogados.com/lc-214/, LC 214 arts. 343–349, plus a market summary at https://www.tradecavalcanti.com.br/publicacoes/reforma-tributaria-lei-complementar-214-2025)

| Year | Milestone |
| --- | --- |
| 2026 | Test year: CBS 0.9% plus IBS 0.1%. Companies that meet their ancillary obligations are not required to pay it, and it can be offset against PIS/Cofins. The test rates do not apply to Simples Nacional. |
| 2027 | CBS charged in full. PIS/Cofins extinguished. IS starts. IBS stays at 0.05% state plus 0.05% municipal in 2027–28. The 2027 CBS rate is the federal rate minus 0.1 pp. |
| 2029–2032 | ICMS and ISS fall to 9/10, 8/10, 7/10 and 6/10 of today's rates while IBS rises. |
| 2033 | ICMS and ISS extinguished. Full IBS/CBS. |

- ICMS tax benefits are compensated until 2032 (arts. 384–405, referenced only).
- The TCU calculated the 2027 CBS reference rate at 11.3% on 28 Oct 2026. The Senate sets it by resolution.
- Reference rate estimate: about 26.5% combined. If the projection for 2033 exceeds 26.5%, the Executive must propose reductions (art. 475 §§10–11). I did not verify the 27–28% range the brief mentions.
- Rate caps and delay: cashback starts Jan 2027 for CBS and Jan 2029 for IBS (art. 123).
- Market risk: entities have asked to delay effective CBS collection to 2028 (Franco's own news feed, 04/11/2026). This was not verified against official sources.

**Reduced and zero rates** (arts. 125, 127, 128 to 134, 143; https://www.barbieriadvogados.com/lc-214/)
- Zero rate: cesta básica (Annex I), listed medicines and devices, menstrual health products, vegetables, fruits and eggs.
- 60% reduction: health, education, food, hygiene and cleaning products, agricultural products and inputs, cultural production, sport, medicines, medical devices and accessibility devices.
- 30% reduction (art. 127): 18 regulated intellectual professions (lawyers, accountants, architects, engineers, administrators, economists, veterinarians and others). Physicians are excluded because they fall in the 60% group.
- The 30% reduction requires licensed partners, no legal-entity partner, and services delivered by the partners personally.
- A diagnostic should flag regulated professions as a "check eligibility" item. Do not assume eligibility.

**Credits and split payment**
- Credit is broad (crédito financeiro). It arises only when the supplier's tax is effectively paid.
- Split payment: art. 31 requires payment providers to separate IBS/CBS at settlement. The standard method links to the electronic invoice (art. 32). The simplified fixed-percentage method is optional after LC 227 (art. 33). A buyer in the regular regime gets no credit for amounts withheld that way.
- Cash-flow effect: split payment takes the tax at settlement.

**Cashback** (arts. 112–124)
- For CadÚnico families with per-capita income up to half the minimum wage.
- At least 100% of CBS and 20% of IBS on gas cylinders, electricity, water, sewage, piped gas and telecom.
- At least 20% of both on everything else.
- This is mostly a B2C and pricing topic.

**Simples Nacional**
- Simples was kept (EC 132; https://bananasoft.ai/dados/aliquotas-reduzidas-reforma via WebSearch).
- A Simples company can choose to pay IBS and CBS under the regular regime, outside the DAS. This matters when its buyers are B2B and want credits.
- Simples and MEI are exempt from filling IBS/CBS on the invoice in 2026. It starts in January 2027. Regime code must still be right in 2026 (WebSearch result, https://www.jettax.com.br/blog/simples-nacional-e-mei-ibs-e-cbs-na-nota-fiscal-em-2026-entenda-as-regras/).
- Unverified in this research: the exact rule that a buyer takes credit only on the IBS/CBS portion actually paid in the DAS. I know it from general knowledge, so a specialist should confirm it.

**Nota fiscal and ERP**
- NT 2025.002 (NF-e/NFC-e) created the IBS/CBS/IS fields.
- Filling is mandatory for taxable events from 03/08/2026 (Ato Conjunto RFB/CGIBS nº 4/2026).
- Rejection 1115 for a missing group was postponed (NT versions 1.51 and 1.52, still active in homologation).
- Source: https://taxcel.com.br/ibs-cbs-nota-fiscal-2026-obrigatorio-rejeicao and https://www.tax360.com.br/conteudo/nt-nfe-nfce-2025-002-rtc-v152. These are blogs. The official Portal Nacional NF-e document was only found as an old file.

**Financial services** (art. 233)
- Combined IBS and CBS rate on credit operations: 10.85% in 2027–28, rising to 12.5% in 2033.
- This matters if the audience includes lenders.

**Statements a diagnostic could use**

| Profile | Statement |
| --- | --- |
| Simples, B2B-heavy | Opportunity or risk: your clients may prefer suppliers that pass credit. Consider the regular regime option for IBS/CBS. |
| Simples, B2C | Usually little change in 2026–27. Watch the 2027 invoice obligation. |
| Presumido or Real | NF-e IBS/CBS fields are already mandatory since 03/08/2026. Check ERP readiness. PIS/Cofins end in 2027. |
| Services, regulated profession | May qualify for the 30% reduction (art. 127). Check firm structure. |
| Health or education | 60% reduction, which lowers the effective burden. |
| Agro or food | Many items at 60% or zero. Suspension and credit rules apply to in natura sales. |
| Commerce with incentives | ICMS benefits are being compensated until 2032. Plan the phase-out. |
| Exporters | Exports are not taxed, with credit maintained (arts. in LC 214; not verified here). |
| Everyone | Contracts and pricing need review: long contracts spanning 2027–2033 need a price clause. Cash flow is exposed to split payment. |

## 3. Partner specialties around an accounting firm

These come from my judgment plus the Franco service list. I found no external source for the list itself.
- Tax attorneys: litigation, tax rulings and disputes over credits and classification. Franco does not list legal as a service.
- Credit recovery and tax-benefit consultants: Franco already does "recuperação de créditos fiscais", so this could be an in-house or partnered line.
- ERP and invoicing systems integrators: the NF-e IBS/CBS fields and ERP updates.
- Banks and credit, working-capital finance: split payment cash-flow impact.
- Insurance and benefits, HR consulting: Franco already has "RH Diferente".
- Corporate and legal restructuring, M&A and valuation: reorganizations to use the 30% regime or the Simples regular regime.
- Pricing and financial controllers, BPO financeiro: Franco already offers BPO.
- Digital certificate providers.
- ESG: low relevance.

## 4. In this repo

Sources: `/Users/battoni/Work/battoni.dev/dev/public.battoni.dev/README.md` (lines 13–17, 114–116, 161–252) and `public.battoni.dev/api/` and `src/` listings.

| Surface | What it does | Where its data lives |
| --- | --- | --- |
| `conheca` (`src/pages/conheca/`) | The prospect's introduction page. Token-gated. | Copy in `src/data/meet.ts` |
| `seu-projeto` (`src/pages/seu-projeto/`) | The discovery stepper, with questions per client. | `src/data/questions.ts`; handlers `api/seuprojeto/` and `api/submit.ts`. Opening a link posts to Slack. |
| `proposta` (`src/pages/proposta/[slug].astro`) | One proposal per token. | `src/data/proposta*.ts`; `api/proposta.ts` |
| `consultoria` (`src/pages/consultoria/<cliente>/<trilha>`) | Questionnaires per respondent profile. The token picks the track. | Supabase tables in `db/consultoria.sql`; question sets in `lib/consultoria/`; `api/consultoria/` |
| `levantamento` (`src/pages/levantamento/`) | A long-running infrastructure survey of cards. The token picks the survey. | Supabase `db/levantamento.sql`; `lib/levantamento/`; `api/levantamento/` |

- **Tokens:** a token resolves to a record like `{client, company, segment, expiresAt}`. It is stored in Vercel Edge Config as `token:<token>`, or in the `ACCESS_TOKENS` env var as fallback (README lines ~40–60).
- **Hosting:** one Vercel project, several domains, with `middleware.ts` rewriting each gated host. The Vercel limit of 12 functions is handled by dispatchers `[action].ts` (memory note).
- **`company/spec-seuprojeto.md`** (147 lines): behaviour and plumbing spec for the discovery stepper. It has predefined branches, and it is reached from the `meet` CTA with the token carried over.
- **`company/discovery-questions.md`** (378 lines): the pt-BR question source of truth for the stepper, proposal calls and outreach. It asks about the problem, not the spec.

## Could not read
- planalto.gov.br via `curl` (blocked). WebFetch read only parts of LC 214, and it did not return the transition articles (343–349), arts. 125–134 or Simples credit rules directly. Those come from secondary sources (Barbieri, Trade Cavalcanti, bananasoft, taxcel, tax360, jettax).
- Receita Federal and Portal NF-e pages: only an old document turned up. NF-e dates come from blogs.
- The 27–28% reference rate range: only about 26.5% and the TCU 11.3% CBS figure were found.
- Franco main-site body font, and which landing-page PNG is the logo.
- The landing page's rendered layout and any hidden thank-you flow.
- The Franco news pages and "Canal do Titular" content, which I did not open.
- The full text of `company/spec-seuprojeto.md` and `company/discovery-questions.md`: I read only the openings, per the instruction.

## 5. Phase 2 additions (07/11/2026, not fetched — general knowledge, marked to confirm)

No page was fetched for this section; a specialist (or the official NT 2025.002 layout) must confirm
each line before a client sees it. The importer must read these tags defensively.
- NF-e layout: `nfeProc` wraps `NFe` + `protNFe`; the document is `NFe/infNFe` with `Id="NFe<44-digit key>"`.
  Header `ide` (`mod` 55, `serie`, `nNF`, `dhEmi`, `verProc` = the emitting system's name and version),
  `emit` (`CNPJ`, `CRT`), `dest` (`CNPJ` or `CPF`, or absent for a consumer), items `det/prod` and
  `det/imposto`, totals `total/ICMSTot/vNF`.
- The access key's last digit is a mod-11 check digit over the first 43 (weights 2–9 from the right).
- CRT (código de regime tributário) in `emit`: 1 Simples Nacional, 2 Simples with excess sub-limit,
  3 regime normal, 4 Simples Nacional – MEI. The XML does not tell Lucro Presumido from Lucro Real.
- NT 2025.002 adds an `IBSCBS` group under `det/imposto` carrying `CST` and `cClassTrib` and the
  values of IBS (state and municipal) and CBS; in 2026 the test rates CBS 0,9 % and IBS 0,1 % (§2)
  appear there. Exact tag paths vary between NT versions 1.x — confirm against the current NT.
- The reference IBS+CBS rate used in the screens is ~26,5 % (§2 "Could not read": only about 26,5 %
  was found); the Simples supplier's DAS share passed as credit (~3–5 %) is unverified (§2, Simples).
- SEFAZ distribution (NFeDistribuicaoDFe) needs the interested party's digital certificate (A1):
  not reachable from a local build.

## 6. Phase 3 additions (07/11/2026, nothing fetched — sources are this repo and the demo)

What Phase 3 rests on, and how firm each piece is. None of it is a Reforma statement; the assistant's
notes cite §2 line by line.
- **Phase 3 scope and hours:** `battonidev-1` `commercial.md` §1 (contract alert, Express/Prime, RNC
  via Franco ~15 % per firm; the staff assistant "Later") and §3, revised 06/11/2026 (Phase 3 = 185 h:
  contracts 20 · sub-brands 30 · RNC 60 · Reinaldo's briefing 15 · assistant 60). The demo's
  `seed.js` 296–317 puts the same five modules in phase 3.
- **Express and Prime:** only the two Instagram handles are known (§1, Brands). Their audiences,
  taglines, proof points and colours are the demo's placeholders (`store.js` 48–51, W38 of
  `battonidev-1`) — to confirm with Franco before any visitor sees them.
- **RNC:** Franco is an RNC member (§1). The R$ 3.500/month per firm and the 15 % channel share are
  the offer's proposal (`commercial.md` §2, `battonidev-1` plan line 182), not a market fact. The six
  firms are invented.
- **Contracts:** Franco keeps clients' contracts (demo note, `carteira.js`); their real format,
  whether values are yearly or total, and how many exist are unknown. The need for a price clause on
  contracts across 2027–2033 is §2 "Everyone".
- **Assistant notes:** the demo's 10 notes (`fiscal.js` 408–509) restate §2: CBS/IBS 2026 test year
  and 2027 start (Timeline), ICMS/ISS phase-out (Timeline), Simples (Simples Nacional), credit from a
  Simples supplier (§2 marks it unverified → seeded as a draft), split payment (Credits and split
  payment), rejection 1115 (Nota fiscal and ERP), 60 % and 30 % reductions (Reduced and zero rates),
  reference rate and TCU 11,3 % (Timeline), cashback (Cashback). Their reviewers and dates
  (27–31/10/2026) are the demo's invented specialists.
- **Could not read / not checked:** nothing new was fetched for Phase 3; the 2028 CBS postponement
  request (§2) is still unverified against an official source.

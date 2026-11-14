/**
 * A firm's opt-in modules (rnc-4 R16, D15) — the keys `auth/me` and `public/firm` list in
 * `modules` (api `FirmModules::ALL`). Core (the site, the diagnostic, the pipeline, the agenda, the
 * metrics) is always on and never listed. A route closed by one declares `meta.module`, a rail entry
 * `module`; `useUserStore().hasModule(key)` answers for the signed-in person's firm.
 */
enum FIRM_MODULE {
  ASSISTANT = 'assistant',
  BRANDS = 'brands',
  BRIEFING = 'briefing',
  CHANNEL = 'channel',
  CHAT = 'chat',
  CONTRACTS = 'contracts',
  FISCAL = 'fiscal',
  PARTNERS = 'partners',
  PORTFOLIO = 'portfolio',
  RADAR = 'radar',
}

export { FIRM_MODULE };

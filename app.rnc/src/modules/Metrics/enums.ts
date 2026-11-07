/** The funnel's steps, in order (api `FunnelStep`). */
enum FUNNEL_STEP {
  VISITAS = 'visitas',
  CTA = 'cta',
  INICIO = 'inicio',
  CONTATO = 'contato',
  RESULTADO = 'resultado',
  AGENDOU = 'agendou',
  CLIENTE = 'cliente',
}

/** Where a visitor first came from (api `TrafficSource`, W15). */
enum TRAFFIC_SOURCE {
  ORGANICO = 'organico',
  PAGO = 'pago',
  PARCEIRO = 'parceiro',
  DIRETO = 'direto',
}

/** The channel a lead started in (api `LeadChannel`'s public cases, rnc-2 R14). */
enum FUNNEL_CHANNEL {
  FORMULARIO = 'formulario',
  CHAT = 'chat',
}

export { FUNNEL_CHANNEL, FUNNEL_STEP, TRAFFIC_SOURCE };

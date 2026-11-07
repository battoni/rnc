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

export { FUNNEL_STEP, TRAFFIC_SOURCE };

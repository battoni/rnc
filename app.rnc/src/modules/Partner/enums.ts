/** The eight specialties of the network (rnc-1 R21) — the API's `PartnerSpecialty`. */
enum PARTNER_SPECIALTY {
  ADVOCACIA_TRIBUTARIA = 'advocacia_tributaria',
  RECUPERACAO_CREDITOS = 'recuperacao_creditos',
  ERP_NFE = 'erp_nfe',
  CREDITO_CAPITAL_GIRO = 'credito_capital_giro',
  SEGUROS_BENEFICIOS = 'seguros_beneficios',
  SOCIETARIO_SUCESSAO = 'societario_sucessao',
  PRECIFICACAO_CONTROLADORIA = 'precificacao_controladoria',
  CERTIFICADO_DIGITAL = 'certificado_digital',
}

/** Why a lead cannot be referred (R22) — the API's `ReferralService::BLOCKED_*`. */
enum ROUTING_BLOCK {
  CONSENT_MISSING = 'consent_missing',
  CONSENT_REFUSED = 'consent_refused',
}

export { PARTNER_SPECIALTY, ROUTING_BLOCK };

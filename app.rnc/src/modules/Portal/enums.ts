/** Where a referral stands — the API's `ReferralStatus` (rnc-2 R7, D4). */
enum REFERRAL_STATUS {
  ENVIADO = 'enviado',
  ACEITO = 'aceito',
  CONTATO = 'contato',
  REUNIAO = 'reuniao',
  PROPOSTA = 'proposta',
  FECHADO = 'fechado',
  RECUSADO = 'recusado',
}

/** The first-contact clock — the API's `ReferralSla` states (rnc-2 R8). */
enum SLA_STATE {
  OK = 'ok',
  ATENCAO = 'atencao',
  ESTOURADO = 'estourado',
  CUMPRIDO = 'cumprido',
  FORA = 'fora',
  ENCERRADO = 'encerrado',
}

/** The firm's one money model for the whole network (rnc-2 R11, D10) — the API's `PartnerMoneyModel`. */
enum LEDGER_MODEL {
  COMISSAO = 'comissao',
  ASSINATURA = 'assinatura',
}

/** A line of a partner's statement (`LedgerService::LINE_*`): the month's fee, or one closing. */
enum STATEMENT_LINE_KIND {
  SUBSCRIPTION = 'subscription',
  CLOSING = 'closing',
}

export { LEDGER_MODEL, REFERRAL_STATUS, SLA_STATE, STATEMENT_LINE_KIND };

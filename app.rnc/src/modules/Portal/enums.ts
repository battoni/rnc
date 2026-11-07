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

export { REFERRAL_STATUS, SLA_STATE };

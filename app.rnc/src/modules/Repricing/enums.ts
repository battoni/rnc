/** What a diagnostic item is to the client (api `DiagnosticKind`): a risk weighs more in the uplift. */
enum REPRICING_ITEM_KIND {
  OPORTUNIDADE = 'oportunidade',
  RISCO = 'risco',
}

/** The sectors a client is filed under (api `Sector`), the list's first filter. */
enum CLIENT_SECTOR {
  COMERCIO = 'comercio',
  INDUSTRIA = 'industria',
  SERVICOS = 'servicos',
  SAUDE = 'saude',
  EDUCACAO = 'educacao',
  AGRO = 'agro',
}

/** A client's regime (api `RepricingIndexRequest::REGIMES`): known, never the quiz's "não sei". */
enum CLIENT_REGIME {
  SIMPLES = 'simples',
  PRESUMIDO = 'presumido',
  REAL = 'real',
}

export { CLIENT_REGIME, CLIENT_SECTOR, REPRICING_ITEM_KIND };

/** A Radar row's kind (api `DiagnosticKind`): opportunities add money, risks never do. */
enum RADAR_KIND {
  OPORTUNIDADE = 'oportunidade',
  RISCO = 'risco',
}

/** What a row's range covers: a year, or a month for the split payment's held cash (W100). */
enum RADAR_PERIOD {
  ANO = 'ano',
  MES = 'mes',
}

/** Where an item's proof comes from (api `EvidenceBuilder`): the client's XMLs or its cadastro. */
enum RADAR_EVIDENCE_SOURCE {
  CADASTRO = 'cadastro',
  NOTAS = 'notas',
}

export { RADAR_EVIDENCE_SOURCE, RADAR_KIND, RADAR_PERIOD };

/** How the visitor answers step 1 (rnc-1 R9, R10) — the API's `DiagnosticPath`. */
enum DIAGNOSTIC_PATH {
  CNPJ = 'cnpj',
  QUIZ = 'quiz',
}

/**
 * The diagnostic's screens, in order. All but `BOOKING` are the API's `LeadStep` — the "parou em" a
 * lead parcial shows the team. `BOOKING` is the step F4 fills (W20).
 */
enum DIAGNOSTIC_STEP {
  BOOKING = 'agendar',
  COMPANY = 'empresa',
  CONTACT = 'contato',
  PROFILE = 'perfil',
  RESULT = 'resultado',
  REVENUE = 'faturamento',
}

/** The CNPJ lookup's state on screen. */
enum LOOKUP_STATUS {
  FOUND = 'found',
  IDLE = 'idle',
  INVALID = 'invalid',
  RUNNING = 'running',
  UNAVAILABLE = 'unavailable',
}

/** A result item's kind — the API's `DiagnosticKind`. */
enum RESULT_KIND {
  OPPORTUNITY = 'oportunidade',
  RISK = 'risco',
}

export { DIAGNOSTIC_PATH, DIAGNOSTIC_STEP, LOOKUP_STATUS, RESULT_KIND };

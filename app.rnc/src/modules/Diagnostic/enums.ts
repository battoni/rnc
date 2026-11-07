/** How the visitor answers step 1 (rnc-1 R9, R10) — the API's `DiagnosticPath`. */
enum DIAGNOSTIC_PATH {
  CNPJ = 'cnpj',
  QUIZ = 'quiz',
}

/**
 * The diagnostic's screens, in order. All but `BOOKING` (the slot picker) and `BOOKED` (the
 * confirmation) are the API's `LeadStep` — the "parou em" a lead parcial shows the team.
 */
enum DIAGNOSTIC_STEP {
  BOOKED = 'agendado',
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

/** A booking refusal the slot picker acts on — the API's `BookingException` codes. */
enum BOOKING_ERROR {
  ALREADY_BOOKED = 'already_booked',
  EMAIL_REQUIRED = 'email_required',
  SLOT_TAKEN = 'slot_taken',
  SLOT_UNAVAILABLE = 'slot_unavailable',
}

export { BOOKING_ERROR, DIAGNOSTIC_PATH, DIAGNOSTIC_STEP, LOOKUP_STATUS, RESULT_KIND };

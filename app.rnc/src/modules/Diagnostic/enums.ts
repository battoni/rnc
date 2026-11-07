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

/**
 * The chat receptionist's turns, in the demo's order (rnc-2 R12): path, CNPJ, lookup confirm,
 * sector, regime, company, name, contact, consent, B2B share, band, ICMS incentives, export.
 */
enum CHAT_TURN {
  B2B = 'b2b',
  BAND = 'band',
  CNPJ = 'cnpj',
  COMPANY = 'company',
  CONSENT = 'consent',
  CONTACT = 'contact',
  EXPORTA = 'exporta',
  INCENTIVOS = 'incentivos',
  LOOKUP = 'lookup',
  NAME = 'name',
  PATH = 'path',
  REGIME = 'regime',
  SECTOR = 'sector',
}

/** Who said a line of the chat's transcript. */
enum CHAT_SPEAKER {
  RECEPTION = 'reception',
  VISITOR = 'visitor',
}

/**
 * How the visitor answers the diagnostic (rnc-2 R14) — the API's public `LeadChannel` cases. The
 * lead keeps the one it was started in.
 */
enum DIAGNOSTIC_CHANNEL {
  CHAT = 'chat',
  FORM = 'formulario',
}

/** A booking refusal the slot picker acts on — the API's `BookingException` codes. */
enum BOOKING_ERROR {
  ALREADY_BOOKED = 'already_booked',
  EMAIL_REQUIRED = 'email_required',
  SLOT_TAKEN = 'slot_taken',
  SLOT_UNAVAILABLE = 'slot_unavailable',
}

export {
  BOOKING_ERROR,
  CHAT_SPEAKER,
  CHAT_TURN,
  DIAGNOSTIC_CHANNEL,
  DIAGNOSTIC_PATH,
  DIAGNOSTIC_STEP,
  LOOKUP_STATUS,
  RESULT_KIND,
};

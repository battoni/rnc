/** The pipeline's stages (rnc-1 R18) — the API's `LeadStage`, in board order. */
enum LEAD_STAGE {
  VISITANTE = 'visitante',
  LEAD_PARCIAL = 'lead_parcial',
  LEAD = 'lead',
  REUNIAO_MARCADA = 'reuniao_marcada',
  PROPOSTA = 'proposta',
  CLIENTE = 'cliente',
  PERDIDO = 'perdido',
}

/** How a card was moved — the API's `MoveVia`, kept on the timeline line. */
enum MOVE_VIA {
  BUTTON = 'botao',
  DETAIL = 'ficha',
  DRAG = 'arrastar',
}

/** The timeline's line types — the API's `LeadEventType`. */
enum LEAD_EVENT_TYPE {
  CNPJ_LOOKED_UP = 'cnpj.consultado',
  CONTACT_CAPTURED = 'contato.capturado',
  DIAGNOSTIC_STARTED = 'diagnostico.inicio',
  MEETING_BOOKED = 'reuniao.marcada',
  NOTE_ADDED = 'nota.adicionada',
  RESULT_SEEN = 'resultado.visto',
  STAGE_CHANGED = 'etapa.alterada',
  STEP_REACHED = 'diagnostico.passo',
}

export { LEAD_EVENT_TYPE, LEAD_STAGE, MOVE_VIA };

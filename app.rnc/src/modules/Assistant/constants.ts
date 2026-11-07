/** The screens this module owns, linked by name (celer-05): the team's rail links here. */
const ASSISTANT_ROUTE_NAMES = {
  ask: 'assistant',
  knowledge: 'assistant-knowledge',
  questions: 'assistant-questions',
} as const;

/** Every screen reads with `assistant.ask` (equipe, ceo); retiring and the gaps are `assistant.manage` (R10, AM4). */
const ASSISTANT_PERMISSIONS = { ask: 'assistant.ask', manage: 'assistant.manage' } as const;

/** A question's length (`AskRequest`): 3–300 characters. */
const ASSISTANT_QUESTION_LIMITS = { max: 300, min: 3 } as const;

/** A specialist's answer (`AnswerQuestionRequest`): 3–2000 characters. */
const ASSISTANT_ANSWER_LIMITS = { max: 2000, min: 3 } as const;

/** The note form's limits (`StoreNoteRequest`). */
const NOTE_FIELD_LIMITS = {
  answer: 2000,
  keyword: { max: 40, min: 2 },
  keywords: 12,
  question: 200,
  rule: 200,
  sourceLabel: 160,
  sourceUrl: 300,
} as const;

/** How many days before a review runs out the base warns: "N notas vencem em 7 dias" (AM19). */
const NOTE_WARNING_DAYS = 7;

/** The refusals the screens branch on (`AssistantException::CODE_*`). */
const ASSISTANT_ERROR_CODES = {
  alreadyNoted: 'already_noted',
  answered: 'answered',
  notReviewer: 'not_reviewer',
  notWriter: 'not_writer',
  questionNotFound: 'question_not_found',
  retired: 'retired',
} as const;

/** Where the API's days are counted (`AssistantNote::BUSINESS_TIMEZONE`): dates on screen are São Paulo's. */
const ASSISTANT_TIME_ZONE = 'America/Sao_Paulo';

export {
  ASSISTANT_ANSWER_LIMITS,
  ASSISTANT_ERROR_CODES,
  ASSISTANT_PERMISSIONS,
  ASSISTANT_QUESTION_LIMITS,
  ASSISTANT_ROUTE_NAMES,
  ASSISTANT_TIME_ZONE,
  NOTE_FIELD_LIMITS,
  NOTE_WARNING_DAYS,
};

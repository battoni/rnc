/** A person as the assistant names them (`AssistantPersonResource`): a reviewer, an editor, a specialist. */
interface AssistantPerson {
  id: number;
  name: string;
}

/** A served note as the answer carries it (`AnswerNoteResource`). */
interface AnswerNote {
  answer: string;
  /** The day the source was read, `YYYY-MM-DD`. */
  as_of: string;
  id: number;
  question: string;
  reviewed_at: string | null;
  reviewer: AssistantPerson | null;
  rule: string;
  slug: string;
  source_label: string;
  source_url: string;
}

/** `POST /api/assistant/ask`: a served note, or none and the firm's specialists for the hand-off (W50). */
interface AssistantAnswer {
  note: AnswerNote | null;
  question: string;
  specialists: AssistantPerson[];
}

/** A chip: a served note's question, asked by id (`SuggestionResource`). */
interface AssistantSuggestion {
  id: number;
  question: string;
}

type QuestionStatus = 'aberta' | 'respondida';

/** A handed-off question (`QuestionResource`). */
interface AssistantQuestion {
  answer: string | null;
  answered_at: string | null;
  asked_by: AssistantPerson | null;
  created_at: string | null;
  id: number;
  /** The note "Virar nota" turned it into. */
  note_id: number | null;
  question: string;
  specialist: AssistantPerson | null;
  status: QuestionStatus;
}

type NoteStatus = 'aposentada' | 'rascunho' | 'revisada';

/** What the base shows: the stored status, or `vencida` for a review older than 90 days. */
type NoteReviewState = NoteStatus | 'vencida';

/** One note of "Base da Reforma" (`NoteResource`). */
interface KnowledgeNote extends AnswerNote {
  can_review: boolean;
  /** São Paulo days the review has left (0 on day 90); null when the note is not served. */
  expires_in_days: number | null;
  keywords_strong: string[];
  keywords_weak: string[];
  last_edited_by: AssistantPerson | null;
  review_state: NoteReviewState;
  status: NoteStatus;
}

/** `GET /api/assistant/notes`: the notes and "N notas vencem em 7 dias" (AM19). */
interface KnowledgeBase {
  expiring_count: number;
  notes: KnowledgeNote[];
}

/** A note's content, as written and sent (`StoreNoteRequest`); `from_question` for "Virar nota". */
interface NotePayload {
  answer: string;
  as_of: string;
  from_question?: number;
  keywords_strong: string[];
  keywords_weak: string[];
  question: string;
  rule: string;
  source_label: string;
  source_url: string;
}

/** One line of "Perguntas sem nota" (`GapResource`). */
interface AssistantGap {
  count: number;
  last_asked_at: string;
  normalised: string;
  question: string;
}

export type {
  AnswerNote,
  AssistantAnswer,
  AssistantGap,
  AssistantPerson,
  AssistantQuestion,
  AssistantSuggestion,
  KnowledgeBase,
  KnowledgeNote,
  NotePayload,
  NoteReviewState,
  NoteStatus,
  QuestionStatus,
};

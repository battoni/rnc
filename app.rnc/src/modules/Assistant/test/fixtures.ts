import type {
  AnswerNote,
  AssistantAnswer,
  AssistantGap,
  AssistantPerson,
  AssistantQuestion,
  AssistantSuggestion,
  KnowledgeNote,
} from '../interfaces';

/*
 * The API's answers as the Assistant specs need them — the seeded people (`LocalUsersSeeder`) and
 * the demo's `cbs` note (`AssistantNoteSeeder`, facts.md §2), reviewed by Mariana on 05/11/2026.
 */
const MARIANA: AssistantPerson = { id: 3, name: 'Mariana Toledo' };
const CARLOS: AssistantPerson = { id: 4, name: 'Carlos Prado' };
const PATRICIA: AssistantPerson = { id: 2, name: 'Patrícia Gomes' };
const REINALDO: AssistantPerson = { id: 1, name: 'Reinaldo Franco' };

const CBS_NOTE: AnswerNote = {
  answer: 'A CBS começa a ser cobrada em 2027, com alíquota cheia, e o PIS e a Cofins deixam de existir.',
  as_of: '2026-11-05',
  id: 1,
  question: 'Quando começa a cobrança da CBS?',
  reviewed_at: '2026-11-05T13:00:00+00:00',
  reviewer: MARIANA,
  rule: 'LC 214/2025, art. 343',
  slug: 'cbs',
  source_label: 'Barbieri Advogados — Cronograma da Reforma',
  source_url: 'https://www.barbieriadvogados.com/cronograma-reforma-tributaria',
};

const ANSWERED_FIXTURE: AssistantAnswer = {
  note: CBS_NOTE,
  question: 'quando começa a cobrança da CBS',
  specialists: [],
};

const UNANSWERED_FIXTURE: AssistantAnswer = {
  note: null,
  question: 'qual o melhor ERP',
  specialists: [CARLOS, MARIANA],
};

const SUGGESTIONS_FIXTURE: AssistantSuggestion[] = [
  { id: 1, question: 'Quando começa a cobrança da CBS?' },
  { id: 2, question: 'O que é o split payment?' },
];

const OPEN_QUESTION_FIXTURE: AssistantQuestion = {
  answer: null,
  answered_at: null,
  asked_by: PATRICIA,
  created_at: '2026-11-19T13:00:00+00:00',
  id: 11,
  note_id: null,
  question: 'Qual o melhor ERP para o Simples?',
  specialist: MARIANA,
  status: 'aberta',
};

const ANSWERED_QUESTION_FIXTURE: AssistantQuestion = {
  ...OPEN_QUESTION_FIXTURE,
  answer: 'Depende do volume de notas; a Franco indica dois parceiros.',
  answered_at: '2026-11-19T14:00:00+00:00',
  id: 12,
  question: 'O Simples perde crédito na Reforma?',
  status: 'respondida',
};

const CARLOS_QUESTION_FIXTURE: AssistantQuestion = {
  ...OPEN_QUESTION_FIXTURE,
  id: 13,
  question: 'Como fica o ISS de quem presta serviço em outro município?',
  specialist: CARLOS,
};

function knowledgeNote(overrides: Partial<KnowledgeNote>): KnowledgeNote {
  return {
    ...CBS_NOTE,
    can_review: false,
    expires_in_days: 87,
    keywords_strong: ['cbs'],
    keywords_weak: ['cobranca', 'comeca'],
    last_edited_by: REINALDO,
    review_state: 'revisada',
    status: 'revisada',
    ...overrides,
  };
}

const KNOWLEDGE_NOTES_FIXTURE: KnowledgeNote[] = [
  knowledgeNote({}),
  knowledgeNote({
    expires_in_days: 5,
    id: 2,
    question: 'O que é o split payment?',
    rule: 'LC 214/2025, arts. 31 a 35',
    slug: 'split',
  }),
  knowledgeNote({
    can_review: true,
    expires_in_days: null,
    id: 3,
    question: 'O Simples gera crédito para quem compra dele?',
    review_state: 'rascunho',
    reviewed_at: null,
    reviewer: null,
    slug: 'credito-simples',
    status: 'rascunho',
  }),
  knowledgeNote({
    can_review: true,
    expires_in_days: null,
    id: 4,
    question: 'Quando o ICMS acaba?',
    review_state: 'vencida',
    reviewed_at: '2026-08-01T13:00:00+00:00',
    slug: 'icms',
  }),
  knowledgeNote({
    expires_in_days: null,
    id: 5,
    question: 'O IPI acaba em 2027?',
    review_state: 'aposentada',
    slug: 'ipi',
    status: 'aposentada',
  }),
];

const GAPS_FIXTURE: AssistantGap[] = [
  {
    count: 2,
    last_asked_at: '2026-11-18T13:00:00+00:00',
    normalised: 'quando comeca a cbs',
    question: 'Quando começa a CBS?',
  },
  {
    count: 1,
    last_asked_at: '2026-11-17T13:00:00+00:00',
    normalised: 'qual o melhor erp',
    question: 'qual o melhor ERP',
  },
];

export {
  ANSWERED_FIXTURE,
  ANSWERED_QUESTION_FIXTURE,
  CARLOS,
  CARLOS_QUESTION_FIXTURE,
  CBS_NOTE,
  GAPS_FIXTURE,
  KNOWLEDGE_NOTES_FIXTURE,
  MARIANA,
  OPEN_QUESTION_FIXTURE,
  PATRICIA,
  REINALDO,
  SUGGESTIONS_FIXTURE,
  UNANSWERED_FIXTURE,
  knowledgeNote,
};

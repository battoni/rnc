import type { ChatInput, ChoiceOption, DiagnosticContactDraft, DiagnosticProfile } from './interfaces';
import { CHAT_TURN, DIAGNOSTIC_STEP } from './enums';

/** The six sectors (the API's `Sector`, the site's page slugs). */
const SECTOR_OPTIONS: ChoiceOption[] = ['comercio', 'industria', 'servicos', 'saude', 'educacao', 'agro'].map(
  (value) => ({ label: `diagnostic.sectors.${value}`, value })
);

/** The quiz's regimes (R10): Simples · Presumido · Real · não sei. */
const REGIME_OPTIONS: ChoiceOption[] = ['simples', 'presumido', 'real', 'nao_sei'].map((value) => ({
  label: `diagnostic.regimes.${value}`,
  value,
}));

/** The faturamento bands (the API's `RevenueBand`). */
const BAND_OPTIONS: ChoiceOption[] = ['b1', 'b2', 'b3', 'b4', 'b5'].map((value) => ({
  label: `diagnostic.bands.${value}`,
  value,
}));

/** The share of sales to companies, in steps of 10 % (the demo's `B2B_SHARES`). */
const B2B_STEP = 10;

const B2B_OPTIONS: ChoiceOption[] = Array.from({ length: 11 }, (_, index) => ({
  label: 'diagnostic.fields.b2bOption',
  value: index * B2B_STEP,
}));

/** The registries the CNPJ path reads, named on screen while it waits (R9): Receita · Simples · CNAE. */
const LOOKUP_STEPS = ['receita', 'simples', 'cnae'] as const;

/** How long each line of the lookup sequence shows before the next — the demo's 450 ms. */
const LOOKUP_STEP_MS = 450;

const CNPJ_LENGTH = 14;

/** The four numbered steps of the progress bar; the contact belongs to the first. */
const PROGRESS_STEPS = [
  { key: 'company', steps: [DIAGNOSTIC_STEP.COMPANY, DIAGNOSTIC_STEP.CONTACT] },
  { key: 'profile', steps: [DIAGNOSTIC_STEP.PROFILE] },
  { key: 'revenue', steps: [DIAGNOSTIC_STEP.REVENUE] },
  { key: 'result', steps: [DIAGNOSTIC_STEP.RESULT, DIAGNOSTIC_STEP.BOOKING, DIAGNOSTIC_STEP.BOOKED] },
] as const;

const EMPTY_PROFILE: DiagnosticProfile = {
  b2b: null,
  band: null,
  exporta: false,
  incentivos: false,
  regime: null,
  sector: null,
};

/** The contact as it is typed, shared by the form and the chat until it is saved (rnc-2 R14). */
const EMPTY_CONTACT: DiagnosticContactDraft = {
  company: '',
  consent: false,
  email: '',
  name: '',
  phone: '',
};

/** The simulator's colour per tax: warm for the taxes that leave, the brand's blues for the two that come. */
const TAX_SWATCH_CLASSES: Record<string, string> = {
  cbs: 'bg-primary-400',
  ibs: 'bg-primary-700',
  icms: 'bg-warn-400',
  iss: 'bg-danger-300',
  'pis-cofins': 'bg-warn-200',
};

/** The screens this module owns besides the site's `/diagnostico` — linked by name (celer-05). */
const DIAGNOSTIC_ROUTE_NAMES = { report: 'site-report' } as const;

/**
 * The chat receptionist's turn table (rnc-2 R12): the demo's 13 turns in order, each with the
 * diagnostic step it answers — the chat saves through the same `useDiagnostic` step as the form,
 * so a turn of the contact belongs to `contato` and the B2B share to `perfil`.
 */
const CHAT_TURNS: { step: DIAGNOSTIC_STEP; turn: CHAT_TURN }[] = [
  { step: DIAGNOSTIC_STEP.COMPANY, turn: CHAT_TURN.PATH },
  { step: DIAGNOSTIC_STEP.COMPANY, turn: CHAT_TURN.CNPJ },
  { step: DIAGNOSTIC_STEP.COMPANY, turn: CHAT_TURN.LOOKUP },
  { step: DIAGNOSTIC_STEP.COMPANY, turn: CHAT_TURN.SECTOR },
  { step: DIAGNOSTIC_STEP.COMPANY, turn: CHAT_TURN.REGIME },
  { step: DIAGNOSTIC_STEP.CONTACT, turn: CHAT_TURN.COMPANY },
  { step: DIAGNOSTIC_STEP.CONTACT, turn: CHAT_TURN.NAME },
  { step: DIAGNOSTIC_STEP.CONTACT, turn: CHAT_TURN.CONTACT },
  { step: DIAGNOSTIC_STEP.CONTACT, turn: CHAT_TURN.CONSENT },
  { step: DIAGNOSTIC_STEP.PROFILE, turn: CHAT_TURN.B2B },
  { step: DIAGNOSTIC_STEP.REVENUE, turn: CHAT_TURN.BAND },
  { step: DIAGNOSTIC_STEP.REVENUE, turn: CHAT_TURN.INCENTIVOS },
  { step: DIAGNOSTIC_STEP.REVENUE, turn: CHAT_TURN.EXPORTA },
];

/** The box of every other turn: typed text is read as one of its options (R13). */
const CHAT_ANSWER_INPUT: ChatInput = {
  autocomplete: 'off',
  inputmode: 'text',
  label: 'diagnostic.chat.inputs.answer',
  placeholder: 'diagnostic.chat.inputs.answerPlaceholder',
};

/** The boxes that differ from `CHAT_ANSWER_INPUT`: the free-text turns, and the B2B share's number pad. */
const CHAT_INPUTS: Partial<Record<CHAT_TURN, ChatInput>> = {
  [CHAT_TURN.B2B]: { ...CHAT_ANSWER_INPUT, inputmode: 'numeric' },
  [CHAT_TURN.CNPJ]: {
    autocomplete: 'off',
    inputmode: 'numeric',
    label: 'diagnostic.chat.inputs.cnpj',
    placeholder: 'diagnostic.chat.inputs.cnpjPlaceholder',
  },
  [CHAT_TURN.COMPANY]: {
    autocomplete: 'organization',
    inputmode: 'text',
    label: 'diagnostic.chat.inputs.company',
    placeholder: 'diagnostic.chat.inputs.companyPlaceholder',
  },
  [CHAT_TURN.CONTACT]: {
    autocomplete: 'email',
    inputmode: 'email',
    label: 'diagnostic.chat.inputs.contact',
    placeholder: 'diagnostic.chat.inputs.contactPlaceholder',
  },
  [CHAT_TURN.NAME]: {
    autocomplete: 'name',
    inputmode: 'text',
    label: 'diagnostic.chat.inputs.name',
    placeholder: 'diagnostic.chat.inputs.namePlaceholder',
  },
};

/** The two answers of a yes/no turn (ICMS incentives, export) — the demo's `yesNo`. */
const CHAT_YES = 'sim';

const CHAT_NO = 'nao';

/** The consent turn's two answers: the visitor authorises the introduction, or does not (AM12). */
const CHAT_CONSENT_YES = 'autorizo';

const CHAT_CONSENT_NO = 'nao_autorizo';

/** The B2B share is a percentage: a typed number outside it is not an answer. */
const B2B_MAX = 100;

/** The agenda's zone (plan AM9): every meeting time is shown in it, wherever the visitor is. */
const MEETING_TIME_ZONE = 'America/Sao_Paulo';

export {
  B2B_MAX,
  B2B_OPTIONS,
  BAND_OPTIONS,
  CHAT_ANSWER_INPUT,
  CHAT_CONSENT_NO,
  CHAT_CONSENT_YES,
  CHAT_INPUTS,
  CHAT_NO,
  CHAT_TURNS,
  CHAT_YES,
  CNPJ_LENGTH,
  DIAGNOSTIC_ROUTE_NAMES,
  EMPTY_CONTACT,
  EMPTY_PROFILE,
  LOOKUP_STEP_MS,
  LOOKUP_STEPS,
  MEETING_TIME_ZONE,
  PROGRESS_STEPS,
  REGIME_OPTIONS,
  SECTOR_OPTIONS,
  TAX_SWATCH_CLASSES,
};

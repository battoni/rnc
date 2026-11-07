import type { ChatAnswer, ChatContact, ChatContext, ChatPrompt, ChatReply, ChoiceOption } from '../interfaces';
import {
  B2B_MAX,
  B2B_OPTIONS,
  BAND_OPTIONS,
  CHAT_ANSWER_INPUT,
  CHAT_CONSENT_NO,
  CHAT_CONSENT_YES,
  CHAT_INPUTS,
  CHAT_NO,
  CHAT_YES,
  REGIME_OPTIONS,
  SECTOR_OPTIONS,
} from '../constants';
import { CHAT_TURN, DIAGNOSTIC_PATH, LOOKUP_STATUS } from '../enums';

/*
 * The chat receptionist's script (rnc-2 R12–R13, D11): pure, no Vue, no LLM. For a turn it says what
 * the reception asks and which quick replies it offers, and it reads what the visitor typed: free
 * text where the turn asks for it (CNPJ, company, name, contact), elsewhere one of the turn's own
 * options — accents and case ignored, "sim/não", a sector or regime name, a number for the B2B
 * share. Text that matches no option, or more than one, is "Não entendi…": the script never picks
 * for the visitor and never answers anything about the Reforma.
 */
const NOT_UNDERSTOOD = 'diagnostic.chat.notUnderstood';

/** The turns that take the visitor's own words (R13); every other turn reads them as an option. */
const FREE_TEXT_TURNS = [CHAT_TURN.CNPJ, CHAT_TURN.COMPANY, CHAT_TURN.NAME, CHAT_TURN.CONTACT];

const CONTACT_INVALID = 'diagnostic.chat.contactInvalid';

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const PHONE_CHARACTERS = /^[\d\s()+.-]+$/;

const NON_DIGITS = /\D/g;

const INTEGER = /-?\d+/;

/** A Brazilian number with the area code, with or without the country's 55 (the API's `PhoneNumber`). */
const PHONE_DIGITS = { countryCode: '55', max: 11, min: 10 } as const;

/** Phrases that say "I am not sure": never read as the "não" of a yes/no turn. */
const UNSURE = ['nao sei', 'talvez', 'sei la'];

/** Words a visitor types for a yes and a no, besides the buttons' own labels. */
const YES_WORDS = ['sim', 's', 'yes', 'claro', 'tenho', 'pode'];

const NO_WORDS = ['nao', 'n', 'no'];

/** The quick replies of the turns whose options are not a form's choice group. */
const PATH_REPLIES: ChatReply[] = [
  { label: 'diagnostic.chat.replies.hasCnpj', value: DIAGNOSTIC_PATH.CNPJ },
  { label: 'diagnostic.chat.replies.noCnpj', value: DIAGNOSTIC_PATH.QUIZ },
];

/** A company found: confirm it, or — not theirs — another CNPJ or the questionnaire, as when none was found. */
const FOUND_REPLIES: ChatReply[] = [
  { label: 'diagnostic.chat.replies.confirmCompany', value: CHAT_YES },
  { label: 'diagnostic.chat.replies.otherCnpj', value: DIAGNOSTIC_PATH.CNPJ },
  { label: 'diagnostic.chat.replies.useQuiz', value: DIAGNOSTIC_PATH.QUIZ },
];

const UNAVAILABLE_REPLIES: ChatReply[] = [
  { label: 'diagnostic.chat.replies.useQuiz', value: DIAGNOSTIC_PATH.QUIZ },
  { label: 'diagnostic.chat.replies.otherCnpj', value: DIAGNOSTIC_PATH.CNPJ },
];

const YES_NO_REPLIES: ChatReply[] = [
  { label: 'diagnostic.chat.replies.yes', value: CHAT_YES },
  { label: 'diagnostic.chat.replies.no', value: CHAT_NO },
];

const CONSENT_REPLIES: ChatReply[] = [
  { label: 'diagnostic.chat.replies.authorize', value: CHAT_CONSENT_YES },
  { label: 'diagnostic.chat.replies.decline', value: CHAT_CONSENT_NO },
];

/** The extra words each value answers to, beyond its value and its label. */
const ALIASES: Record<string, string[]> = {
  [CHAT_CONSENT_NO]: [...NO_WORDS, 'prefiro nao', 'nao autorizo'],
  [CHAT_CONSENT_YES]: [...YES_WORDS, 'autorizo'],
  [CHAT_NO]: NO_WORDS,
  [CHAT_YES]: YES_WORDS,
};

const PATH_ALIASES: Record<string, string[]> = {
  [DIAGNOSTIC_PATH.CNPJ]: [...YES_WORDS, 'cnpj'],
  [DIAGNOSTIC_PATH.QUIZ]: [...NO_WORDS, 'nao tenho', 'questionario', 'perguntas'],
};

/** The lookup's replies: "sim" confirms a company found, the paths go back to another CNPJ or the quiz. */
const LOOKUP_REPLY_ALIASES: Record<string, string[]> = {
  [CHAT_YES]: YES_WORDS,
  [DIAGNOSTIC_PATH.CNPJ]: ['cnpj', 'outro', 'outro cnpj', 'tentar', 'nao e essa', 'nao e ela', 'outra empresa'],
  [DIAGNOSTIC_PATH.QUIZ]: ['questionario', 'perguntas'],
};

// HELPERS
function normalize(text: string): string {
  return text
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, ' ')
    .trim();
}

function tokens(text: string): string[] {
  return text ? text.split(' ') : [];
}

function optionReplies(options: ChoiceOption[]): ChatReply[] {
  return options.map((option) => ({
    label: option.label,
    params: { value: option.value },
    value: String(option.value),
  }));
}

/**
 * How well a candidate (a value, a label or an alias, normalized) names the typed text: its length in
 * words when the text holds it whole, half that when the text is a fragment of it, 0 otherwise.
 */
function scoreOf(typed: string, candidate: string): number {
  if (!candidate) return 0;

  const candidateWords = tokens(candidate);

  if (` ${typed} `.includes(` ${candidate} `)) return candidateWords.length * 2;

  const typedWords = tokens(typed);

  return typedWords.every((word) => candidateWords.includes(word)) ? typedWords.length : 0;
}

/** The one reply the typed text names best; none when two replies tie or the visitor is unsure. */
function pickReply(text: string, replies: ChatReply[], labels: ChatContext['labels'], aliases = ALIASES) {
  const typed = normalize(text);

  if (!typed) return null;

  const scored = replies.map((reply) => {
    const candidates = [
      reply.value.replace(/_/g, ' '),
      ...(reply.text ? [reply.text] : labels(reply.label ?? '', reply.params)),
      ...(aliases[reply.value] ?? []),
    ].map(normalize);
    const best = Math.max(...candidates.map((candidate) => scoreOf(typed, candidate)));
    const isUnsureAnswer = candidates.some((candidate) => UNSURE.includes(candidate));

    return { best, isUnsureAnswer, value: reply.value };
  });
  const isUnsure = UNSURE.some((phrase) => ` ${typed} `.includes(` ${phrase} `));
  const top = Math.max(...scored.map((entry) => entry.best));
  const winners = scored.filter((entry) => entry.best === top);
  const [winner] = winners;

  if (!winner || top === 0 || winners.length !== 1) return null;

  return isUnsure && !winner.isUnsureAnswer ? null : winner.value;
}

function readShare(text: string): string | null {
  const found = text.match(INTEGER);
  const share = found ? Number(found[0]) : NaN;

  return Number.isInteger(share) && share >= 0 && share <= B2B_MAX ? String(share) : null;
}

function repliesOf(turn: CHAT_TURN, context: ChatContext): ChatReply[] {
  switch (turn) {
    case CHAT_TURN.PATH:
      return PATH_REPLIES;
    case CHAT_TURN.LOOKUP:
      if (context.lookupStatus === LOOKUP_STATUS.FOUND) return FOUND_REPLIES;

      return context.lookupStatus === LOOKUP_STATUS.UNAVAILABLE ? UNAVAILABLE_REPLIES : [];
    case CHAT_TURN.SECTOR:
      return optionReplies(SECTOR_OPTIONS);
    case CHAT_TURN.REGIME:
      return optionReplies(REGIME_OPTIONS);
    case CHAT_TURN.COMPANY:
      return context.company ? [{ text: context.company.name, value: context.company.name }] : [];
    case CHAT_TURN.CONSENT:
      return CONSENT_REPLIES;
    case CHAT_TURN.B2B:
      return optionReplies(B2B_OPTIONS);
    case CHAT_TURN.BAND:
      return optionReplies(BAND_OPTIONS);
    case CHAT_TURN.INCENTIVOS:
    case CHAT_TURN.EXPORTA:
      return YES_NO_REPLIES;
    default:
      return [];
  }
}

function askOf(turn: CHAT_TURN, context: ChatContext): { ask: string; askParams: Record<string, unknown> } {
  if (turn === CHAT_TURN.CONSENT)
    return { ask: 'diagnostic.chat.asks.consent', askParams: { text: context.consentText } };

  if (turn !== CHAT_TURN.LOOKUP) return { ask: `diagnostic.chat.asks.${turn}`, askParams: {} };

  if (context.lookupStatus === LOOKUP_STATUS.FOUND && context.company) {
    const { name, regime, sector } = context.company;

    return {
      ask: regime ? 'diagnostic.chat.asks.lookupFound' : 'diagnostic.chat.asks.lookupFoundNoRegime',
      askParams: {
        name,
        regime: regime ? context.translate(`diagnostic.regimes.${regime}`) : '',
        sector: context.translate(`diagnostic.sectors.${sector}`).toLowerCase(),
      },
    };
  }

  return {
    ask:
      context.lookupStatus === LOOKUP_STATUS.UNAVAILABLE
        ? 'diagnostic.chat.asks.lookupUnavailable'
        : 'diagnostic.chat.asks.lookupRunning',
    askParams: {},
  };
}

// SCRIPT
/** What the reception says at a turn, with its quick replies and its box. */
function promptOf(turn: CHAT_TURN, context: ChatContext): ChatPrompt {
  const isLookupRunning =
    turn === CHAT_TURN.LOOKUP &&
    context.lookupStatus !== LOOKUP_STATUS.FOUND &&
    context.lookupStatus !== LOOKUP_STATUS.UNAVAILABLE;

  return {
    ...askOf(turn, context),
    busy: isLookupRunning,
    input: isLookupRunning ? null : (CHAT_INPUTS[turn] ?? CHAT_ANSWER_INPUT),
    isFreeText: FREE_TEXT_TURNS.includes(turn),
    replies: repliesOf(turn, context),
    turn,
  };
}

/** One way to reach the visitor: a whole e-mail, or a phone with its area code. */
function readContact(text: string): ChatContact | null {
  const typed = text.trim();

  if (typed.includes('@')) return EMAIL_PATTERN.test(typed) ? { email: typed, phone: null } : null;

  const digits = typed.replace(NON_DIGITS, '');
  const national =
    digits.length > PHONE_DIGITS.max && digits.startsWith(PHONE_DIGITS.countryCode) ? digits.slice(2) : digits;
  const isPhone =
    PHONE_CHARACTERS.test(typed) && national.length >= PHONE_DIGITS.min && national.length <= PHONE_DIGITS.max;

  return isPhone ? { email: null, phone: typed } : null;
}

/** What the visitor typed, read against the turn. */
function matchAnswer(turn: CHAT_TURN, text: string, context: ChatContext): ChatAnswer {
  const typed = text.trim();
  const refused = (errorKey: string): ChatAnswer => ({ errorKey, value: null });
  const taken = (value: string | null): ChatAnswer =>
    value === null ? refused(NOT_UNDERSTOOD) : { errorKey: null, value };

  if (FREE_TEXT_TURNS.includes(turn)) {
    if (!typed) return refused(NOT_UNDERSTOOD);

    return turn === CHAT_TURN.CONTACT && !readContact(typed) ? refused(CONTACT_INVALID) : taken(typed);
  }

  const replies = repliesOf(turn, context);

  if (turn === CHAT_TURN.B2B) return taken(readShare(typed));

  if (turn === CHAT_TURN.PATH) return taken(pickReply(typed, replies, context.labels, PATH_ALIASES));

  if (turn === CHAT_TURN.LOOKUP) return taken(pickReply(typed, replies, context.labels, LOOKUP_REPLY_ALIASES));

  return taken(pickReply(typed, replies, context.labels));
}

const ReceptionistScript = { matchAnswer, promptOf, readContact };

export { ReceptionistScript };

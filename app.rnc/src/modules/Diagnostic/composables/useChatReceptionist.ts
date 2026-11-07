import { computed, ref, watch } from 'vue';
import type { Ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { ChatContext, ChatMessage, ChatPrompt, ChatReply } from '../interfaces';
import type { UseDiagnostic } from './useDiagnostic';
import { UI_LOCALES } from '@Constants';
import { CHAT_CONSENT_YES, CHAT_TURNS, CHAT_YES } from '../constants';
import { CHAT_SPEAKER, CHAT_TURN, DIAGNOSTIC_CHANNEL, DIAGNOSTIC_PATH, DIAGNOSTIC_STEP, LOOKUP_STATUS } from '../enums';
import { ReceptionistScript } from './receptionistScript';

/** The steps after the questions: the chat shows the result, the slot picker, the confirmation. */
const DONE_STEPS: DIAGNOSTIC_STEP[] = [DIAGNOSTIC_STEP.RESULT, DIAGNOSTIC_STEP.BOOKING, DIAGNOSTIC_STEP.BOOKED];

/** `useDiagnostic`'s words for a contact the API refused (an invalid phone or e-mail). */
const CONTACT_REFUSED = 'diagnostic.errors.contactInvalid';

/** The form's words for a CNPJ that does not check out. */
const CNPJ_REFUSED = 'diagnostic.cnpj.invalid';

const TURN_STEP = Object.fromEntries(CHAT_TURNS.map((entry) => [entry.turn, entry.step])) as Record<
  CHAT_TURN,
  DIAGNOSTIC_STEP
>;

/**
 * The chat receptionist (rnc-2 R12–R14, D11): a scripted conversation over the SAME `useDiagnostic`
 * the form uses — no second copy of the answers. Each answer is written where the form's field
 * writes it and saved by the form's own step (`confirmCompany`, `saveContact`, `saveProfile`,
 * `finish`), so the chat sends the PATCHes the form sends (AM8) and a switch to the form mid-way
 * finds every answer in place.
 *
 * The turn moves on by itself when the answer needs no saving; otherwise it waits for the
 * diagnostic's step to change. The same watch catches the form moving the step while the chat was
 * hidden, and the talk picks up from there ("Vi que você avançou pelo formulário").
 */
function useChatReceptionist(diagnostic: UseDiagnostic, isActive: Ref<boolean>) {
  const { t } = useI18n();

  const errorKey = ref<string | null>(null);
  const hasStarted = ref(false);
  const log = ref<ChatMessage[]>([]);
  const turn = ref<CHAT_TURN>(CHAT_TURN.PATH);
  const typed = ref('');

  let nextId = 0;

  const context = computed<ChatContext>(() => ({
    company: diagnostic.company.value,
    consentText: diagnostic.consentText.value,
    labels: (key, params) => UI_LOCALES.map((locale) => t(key, params ?? {}, { locale: locale.value })),
    lookupStatus: diagnostic.lookupStatus.value,
    translate: (key, params) => t(key, params ?? {}),
  }));

  const isDone = computed(() => DONE_STEPS.includes(diagnostic.step.value));

  const prompt = computed<ChatPrompt | null>(() =>
    isDone.value ? null : ReceptionistScript.promptOf(turn.value, context.value)
  );

  const isBusy = computed(() => diagnostic.isSaving.value || !!prompt.value?.busy);

  /** What the reception says now: the error of the last answer, or the turn's question. */
  const shownError = computed(() => errorKey.value ?? diagnostic.errorKey.value);

  watch(isActive, onActiveChange, { immediate: true });
  watch(diagnostic.step, onStepChange);
  watch(diagnostic.lookupStatus, onLookupStatus);
  watch(diagnostic.token, onTokenChange);
  watch(diagnostic.errorKey, onDiagnosticError);

  // HELPERS
  function say(key: string, params: Record<string, unknown> = {}) {
    log.value.push({ id: nextId++, key, params, speaker: CHAT_SPEAKER.RECEPTION });
  }

  function hear(text: string) {
    log.value.push({ id: nextId++, speaker: CHAT_SPEAKER.VISITOR, text });
  }

  /** A quick reply is kept as its key, so the transcript follows a language switch; a name stays as typed. */
  function hearReply(reply: ChatReply) {
    if (reply.text !== undefined) return hear(reply.text);

    log.value.push({ id: nextId++, key: reply.label ?? '', params: reply.params ?? {}, speaker: CHAT_SPEAKER.VISITOR });
  }

  /** The line on screen goes into the transcript before the visitor's answer to it. */
  function logLiveLine() {
    if (shownError.value) say(shownError.value);
    else if (prompt.value) say(prompt.value.ask, prompt.value.askParams);
  }

  /** Where a talk picks up when the form got somewhere first. */
  function turnOfStep(step: DIAGNOSTIC_STEP): CHAT_TURN {
    const { company, contact, lookupStatus, path, profile } = diagnostic;

    switch (step) {
      case DIAGNOSTIC_STEP.CONTACT:
        if (!contact.value.company) return CHAT_TURN.COMPANY;
        if (!contact.value.name) return CHAT_TURN.NAME;

        return contact.value.email || contact.value.phone ? CHAT_TURN.CONSENT : CHAT_TURN.CONTACT;
      case DIAGNOSTIC_STEP.PROFILE:
        return CHAT_TURN.B2B;
      case DIAGNOSTIC_STEP.REVENUE:
        return CHAT_TURN.BAND;
      default:
        if (path.value === DIAGNOSTIC_PATH.QUIZ) return profile.value.sector ? CHAT_TURN.REGIME : CHAT_TURN.SECTOR;
        if (path.value !== DIAGNOSTIC_PATH.CNPJ) return CHAT_TURN.PATH;
        if (lookupStatus.value === LOOKUP_STATUS.FOUND && company.value) return CHAT_TURN.LOOKUP;

        return lookupStatus.value === LOOKUP_STATUS.UNAVAILABLE ? CHAT_TURN.LOOKUP : CHAT_TURN.CNPJ;
    }
  }

  function start() {
    hasStarted.value = true;
    say('diagnostic.chat.greeting');

    const hasBegun = !!diagnostic.path.value || diagnostic.step.value !== DIAGNOSTIC_STEP.COMPANY;

    if (!hasBegun) return;

    say('diagnostic.chat.resumed');
    turn.value = turnOfStep(diagnostic.step.value);
  }

  /** Writes the answer where the form's field writes it, then moves on or saves the step. */
  function apply(answered: CHAT_TURN, value: string) {
    const { contact, profile } = diagnostic;

    switch (answered) {
      case CHAT_TURN.PATH:
        diagnostic.choosePath(value as DIAGNOSTIC_PATH);
        turn.value = value === DIAGNOSTIC_PATH.CNPJ ? CHAT_TURN.CNPJ : CHAT_TURN.SECTOR;
        return;
      case CHAT_TURN.CNPJ:
        diagnostic.lookupCnpj(value);

        // Without a lead (its start failed) the diagnostic says it is unavailable and the turn stays.
        if (diagnostic.lookupStatus.value === LOOKUP_STATUS.INVALID) errorKey.value = CNPJ_REFUSED;
        else if (diagnostic.token.value) turn.value = CHAT_TURN.LOOKUP;

        return;
      case CHAT_TURN.LOOKUP:
        return applyLookup(value);
      case CHAT_TURN.SECTOR:
        profile.value.sector = value;
        turn.value = CHAT_TURN.REGIME;
        return;
      case CHAT_TURN.REGIME:
        profile.value.regime = value;
        diagnostic.confirmCompany();
        return;
      case CHAT_TURN.COMPANY:
        contact.value.company = value;
        turn.value = CHAT_TURN.NAME;
        return;
      case CHAT_TURN.NAME:
        contact.value.name = value;
        turn.value = CHAT_TURN.CONTACT;
        return;
      case CHAT_TURN.CONTACT: {
        const reached = ReceptionistScript.readContact(value);

        contact.value.email = reached?.email ?? '';
        contact.value.phone = reached?.phone ?? '';
        turn.value = CHAT_TURN.CONSENT;
        return;
      }
      case CHAT_TURN.CONSENT:
        contact.value.consent = value === CHAT_CONSENT_YES;
        diagnostic.saveContact();
        return;
      case CHAT_TURN.B2B:
        profile.value.b2b = Number(value);
        diagnostic.saveProfile();
        return;
      case CHAT_TURN.BAND:
        profile.value.band = value;
        turn.value = CHAT_TURN.INCENTIVOS;
        return;
      case CHAT_TURN.INCENTIVOS:
        profile.value.incentivos = value === CHAT_YES;
        turn.value = CHAT_TURN.EXPORTA;
        return;
      case CHAT_TURN.EXPORTA:
        profile.value.exporta = value === CHAT_YES;
        diagnostic.finish();
    }
  }

  /**
   * "Sim, está certo" saves step 1 (or asks the regime the registry did not give). "Tentar outro CNPJ"
   * and "Usar o questionário" go back — after a company that was found, forgetting what it filled.
   */
  function applyLookup(value: string) {
    const { company, contact, lookupStatus, profile } = diagnostic;

    if (value === CHAT_YES) {
      if (company.value?.regime) diagnostic.confirmCompany();
      else turn.value = CHAT_TURN.REGIME;

      return;
    }

    if (lookupStatus.value === LOOKUP_STATUS.FOUND) {
      company.value = null;
      contact.value.company = '';
      profile.value = { ...profile.value, regime: null, sector: null };
    }

    if (value === DIAGNOSTIC_PATH.CNPJ) {
      turn.value = CHAT_TURN.CNPJ;
      return;
    }

    diagnostic.choosePath(DIAGNOSTIC_PATH.QUIZ);
    turn.value = CHAT_TURN.SECTOR;
  }

  // EVENTS
  /** A quick reply: the button's own words go into the transcript. */
  function answer(reply: ChatReply) {
    if (!prompt.value || isBusy.value) return;

    const answered = turn.value;

    logLiveLine();
    hearReply(reply);
    errorKey.value = null;
    diagnostic.clearError();
    typed.value = '';
    apply(answered, reply.value);
  }

  /** The box: free text where the turn asks for it, an option read from the words everywhere else. */
  function send() {
    const text = typed.value.trim();

    if (!prompt.value || isBusy.value || !text) return;

    const answered = turn.value;
    const read = ReceptionistScript.matchAnswer(answered, text, context.value);

    logLiveLine();
    hear(text);
    typed.value = '';
    errorKey.value = read.errorKey;
    diagnostic.clearError();

    if (read.value !== null) apply(answered, read.value);
  }

  /** The surface on screen is the channel a lead started now would carry (R14). */
  function onActiveChange(active: boolean) {
    diagnostic.selectChannel(active ? DIAGNOSTIC_CHANNEL.CHAT : DIAGNOSTIC_CHANNEL.FORM);

    if (active && !hasStarted.value) start();
  }

  /**
   * The step moved: by the chat's own answer (the step's first question comes next) or by the form
   * while the chat was hidden (the talk picks up from what the form filled).
   */
  function onStepChange(step: DIAGNOSTIC_STEP) {
    if (DONE_STEPS.includes(step) || TURN_STEP[turn.value] === step) return;

    errorKey.value = null;

    if (isActive.value) {
      turn.value = CHAT_TURNS.find((entry) => entry.step === step)?.turn ?? turnOfStep(step);
      return;
    }

    turn.value = turnOfStep(step);

    if (hasStarted.value) say('diagnostic.chat.advanced');
  }

  /** A CNPJ that does not check out sends the talk back to the CNPJ, with the form's own words. */
  function onLookupStatus(status: LOOKUP_STATUS) {
    if (turn.value !== CHAT_TURN.LOOKUP || status !== LOOKUP_STATUS.INVALID) return;

    turn.value = CHAT_TURN.CNPJ;
    errorKey.value = CNPJ_REFUSED;
  }

  /** The API refused the contact the chat gathered: back to the turn that asked for it. */
  function onDiagnosticError(key: string | null) {
    if (turn.value === CHAT_TURN.CONSENT && key === CONTACT_REFUSED) turn.value = CHAT_TURN.CONTACT;
  }

  /** "Refazer o diagnóstico" clears the token: the talk starts over with it. */
  function onTokenChange(next: string | null, previous: string | null) {
    if (next || !previous) return;

    errorKey.value = null;
    hasStarted.value = false;
    log.value = [];
    turn.value = CHAT_TURN.PATH;
    typed.value = '';

    if (isActive.value) start();
  }

  return {
    answer,
    errorKey: shownError,
    isBusy,
    isDone,
    log,
    prompt,
    send,
    turn,
    typed,
  };
}

type UseChatReceptionist = ReturnType<typeof useChatReceptionist>;

export { useChatReceptionist };
export type { UseChatReceptionist };

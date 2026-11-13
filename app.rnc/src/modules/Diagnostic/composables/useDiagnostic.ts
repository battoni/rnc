import { ref } from 'vue';
import type {
  BookingConfirmation,
  CnpjCompany,
  CnpjLookupResult,
  DiagnosticContactDraft,
  DiagnosticContactPayload,
  DiagnosticProfile,
  DiagnosticResult,
  DiagnosticSimulation,
  PublicLead,
  UpdateDiagnosticPayload,
} from '../interfaces';
import type { ApiResponse } from '@Providers';
import { HTTP_STATUS } from '@Enums';
import { TRACKING_EVENT_TYPE, useTracking } from '@SiteModule';
import { CNPJ_LENGTH, EMPTY_CONTACT, EMPTY_PROFILE, LOOKUP_STEP_MS, LOOKUP_STEPS } from '../constants';
import { DIAGNOSTIC_CHANNEL, DIAGNOSTIC_PATH, DIAGNOSTIC_STEP, LOOKUP_STATUS } from '../enums';
import {
  GetDiagnosticResultService,
  GetDiagnosticSimulationService,
  LookupCnpjService,
  StartDiagnosticService,
  UpdateDiagnosticService,
} from '../services';

const NON_DIGITS = /\D/g;

/**
 * The public diagnostic's state and steps (rnc-1 R9–R14). One instance per visit of the page.
 *
 * Picking a path starts the lead (a `visitante`, AM13) and keeps its token; every later step is
 * saved as the visitor leaves it, so a visitor who gives the contact and goes away is a lead parcial
 * with the step it stopped at (R11). Each funnel step is tracked with the token, never a lead id.
 *
 * The form and the chat receptionist (rnc-2 R12–R14) are two faces of this one instance: both write
 * the same `profile` and the same `contact` draft and save through the same steps, so switching
 * between them mid-way loses nothing and both send the same PATCHes. The channel goes with the
 * start and is fixed from then on (the API keeps the lead's).
 */
function useDiagnostic() {
  const { track, visitContext } = useTracking();

  const booking = ref<BookingConfirmation | null>(null);
  const channel = ref<DIAGNOSTIC_CHANNEL>(DIAGNOSTIC_CHANNEL.FORM);
  const company = ref<CnpjCompany | null>(null);
  const contact = ref<DiagnosticContactDraft>({ ...EMPTY_CONTACT });
  const consentText = ref('');
  const errorKey = ref<string | null>(null);
  const hasEmail = ref(false);
  const isSaving = ref(false);
  const lookupStatus = ref<LOOKUP_STATUS>(LOOKUP_STATUS.IDLE);
  const lookupStepIndex = ref(0);
  const path = ref<DIAGNOSTIC_PATH | null>(null);
  const profile = ref<DiagnosticProfile>({ ...EMPTY_PROFILE });
  const result = ref<DiagnosticResult | null>(null);
  const simulation = ref<DiagnosticSimulation | null>(null);
  const step = ref<DIAGNOSTIC_STEP>(DIAGNOSTIC_STEP.COMPANY);
  const token = ref<string | null>(null);

  // HELPERS
  function fail(key: string) {
    errorKey.value = key;
  }

  function orNull(value: string): string | null {
    return value.trim() || null;
  }

  /** The draft as `UpdateDiagnosticRequest` takes it: trimmed, an empty way to reach is null. */
  function contactPayload(): DiagnosticContactPayload {
    const { company: companyName, consent, email, name, phone } = contact.value;

    return { company: companyName.trim(), consent, email: orNull(email), name: orNull(name), phone: orNull(phone) };
  }

  function save(payload: UpdateDiagnosticPayload): Promise<void> {
    if (!token.value) return Promise.reject(new Error('diagnostic.errors.notStarted'));

    isSaving.value = true;
    errorKey.value = null;

    return UpdateDiagnosticService(token.value, payload)
      .then(() => undefined)
      .finally(() => (isSaving.value = false));
  }

  /** The lookup sequence on screen: one registry after the other, whatever the API's speed. */
  function playLookupSequence(): Promise<void> {
    lookupStepIndex.value = 0;

    return new Promise((resolve) => {
      const timer = setInterval(() => {
        lookupStepIndex.value += 1;

        if (lookupStepIndex.value < LOOKUP_STEPS.length) return;

        clearInterval(timer);
        resolve();
      }, LOOKUP_STEP_MS);
    });
  }

  function onLookupAnswered({ data }: ApiResponse<CnpjLookupResult>) {
    company.value = data.company;
    profile.value = { ...profile.value, ...data.lead.profile };

    if (data.company && !contact.value.company) contact.value.company = data.company.name;

    lookupStatus.value = data.company ? LOOKUP_STATUS.FOUND : LOOKUP_STATUS.UNAVAILABLE;
  }

  function onLookupFailed(error: { response?: { status?: number } }) {
    const isRefusedCnpj = error.response?.status === HTTP_STATUS.UNPROCESSABLE_ENTITY;

    lookupStatus.value = isRefusedCnpj ? LOOKUP_STATUS.INVALID : LOOKUP_STATUS.UNAVAILABLE;
  }

  /** Starts the lead (a `visitante`, AM13) for the path; a failure is said and the next try starts it again. */
  function startLead(next: DIAGNOSTIC_PATH) {
    const { medium, source, visitor } = visitContext();
    const PAYLOAD = { channel: channel.value, medium, path: next, sector: profile.value.sector, source, visitor };

    isSaving.value = true;

    StartDiagnosticService(PAYLOAD)
      .then(onStarted)
      .catch(() => fail('diagnostic.errors.unavailable'))
      .finally(() => (isSaving.value = false));
  }

  function onStarted({ data }: ApiResponse<PublicLead>) {
    token.value = data.token ?? null;
    consentText.value = data.consent.text;

    track(TRACKING_EVENT_TYPE.DIAGNOSTIC_START, profile.value.sector, token.value);
  }

  // EVENTS
  /** The sector a sector page's CTA carried (`?setor=`), before the visitor answers anything. */
  function presetSector(sector: string | null) {
    profile.value.sector = sector;
  }

  function choosePath(next: DIAGNOSTIC_PATH) {
    path.value = next;
    errorKey.value = null;

    if (token.value || isSaving.value) return;

    startLead(next);
  }

  /** The error said for the last answer goes once the visitor answers again (the chat's next turn). */
  function clearError() {
    errorKey.value = null;
  }

  /** Form or chat, as the visitor shows it — sent with the start; once the lead exists it stays. */
  function selectChannel(next: DIAGNOSTIC_CHANNEL) {
    if (token.value) return;

    channel.value = next;
  }

  function lookupCnpj(typed: string) {
    const digits = typed.replace(NON_DIGITS, '');

    company.value = null;

    if (digits.length !== CNPJ_LENGTH) {
      lookupStatus.value = LOOKUP_STATUS.INVALID;
      return;
    }

    // The start failed, so there is no lead to look the CNPJ up for: not the CNPJ's fault. Say so and
    // start it again, so the visitor's next try can go through.
    if (!token.value) {
      lookupStatus.value = LOOKUP_STATUS.IDLE;
      fail('diagnostic.errors.unavailable');

      if (!isSaving.value) startLead(DIAGNOSTIC_PATH.CNPJ);

      return;
    }

    lookupStatus.value = LOOKUP_STATUS.RUNNING;

    const LOOKUP = LookupCnpjService(token.value, digits);

    Promise.all([LOOKUP, playLookupSequence()])
      .then(([answer]) => onLookupAnswered(answer))
      .catch(onLookupFailed);
  }

  function confirmCompany() {
    const { regime, sector } = profile.value;

    if (!sector || !regime) {
      fail('diagnostic.errors.companyStep');
      return;
    }

    save({ profile: { regime, sector }, step: DIAGNOSTIC_STEP.CONTACT })
      .then(() => (step.value = DIAGNOSTIC_STEP.CONTACT))
      .catch(() => fail('diagnostic.errors.unavailable'));
  }

  function saveContact() {
    const CONTACT = contactPayload();
    const hasWayToReach = !!CONTACT.phone || !!CONTACT.email;

    if (!CONTACT.company || !hasWayToReach) {
      fail('diagnostic.errors.contact');
      return;
    }

    save({ contact: CONTACT, step: DIAGNOSTIC_STEP.PROFILE })
      .then(() => {
        hasEmail.value = !!CONTACT.email;
        step.value = DIAGNOSTIC_STEP.PROFILE;

        track(TRACKING_EVENT_TYPE.DIAGNOSTIC_CONTACT, profile.value.sector, token.value);
      })
      .catch(() => fail('diagnostic.errors.contactInvalid'));
  }

  function saveProfile() {
    const { b2b, regime, sector } = profile.value;

    if (b2b === null || !sector || !regime) {
      fail('diagnostic.errors.profileStep');
      return;
    }

    save({ profile: { b2b, regime, sector }, step: DIAGNOSTIC_STEP.REVENUE })
      .then(() => (step.value = DIAGNOSTIC_STEP.REVENUE))
      .catch(() => fail('diagnostic.errors.unavailable'));
  }

  function finish() {
    const { band, exporta, incentivos } = profile.value;

    if (!band) {
      fail('diagnostic.errors.revenueStep');
      return;
    }

    save({ profile: { band, exporta, incentivos }, step: DIAGNOSTIC_STEP.RESULT })
      .then(() => Promise.all([GetDiagnosticResultService(token.value!), GetDiagnosticSimulationService(token.value!)]))
      .then(([resultAnswer, simulationAnswer]) => {
        result.value = resultAnswer.data;
        simulation.value = simulationAnswer.data;
        step.value = DIAGNOSTIC_STEP.RESULT;

        track(TRACKING_EVENT_TYPE.DIAGNOSTIC_RESULT, profile.value.sector, token.value);
      })
      .catch(() => fail('diagnostic.errors.unavailable'));
  }

  /** "Agendar com um especialista" → the slot picker (R15). */
  function openBooking() {
    errorKey.value = null;
    step.value = DIAGNOSTIC_STEP.BOOKING;
  }

  /** The slot is booked (R16): the confirmation, and the funnel's "agendou" with the token. */
  function confirmBooking(confirmation: BookingConfirmation) {
    booking.value = confirmation;
    step.value = DIAGNOSTIC_STEP.BOOKED;

    track(TRACKING_EVENT_TYPE.BOOKING, profile.value.sector, token.value);
  }

  function restart() {
    booking.value = null;
    company.value = null;
    contact.value = { ...EMPTY_CONTACT };
    errorKey.value = null;
    hasEmail.value = false;
    lookupStatus.value = LOOKUP_STATUS.IDLE;
    path.value = null;
    profile.value = { ...EMPTY_PROFILE };
    result.value = null;
    simulation.value = null;
    step.value = DIAGNOSTIC_STEP.COMPANY;
    token.value = null;
  }

  return {
    booking,
    channel,
    choosePath,
    clearError,
    company,
    confirmBooking,
    confirmCompany,
    consentText,
    contact,
    errorKey,
    finish,
    hasEmail,
    isSaving,
    lookupCnpj,
    lookupStatus,
    lookupStepIndex,
    openBooking,
    path,
    presetSector,
    profile,
    restart,
    result,
    saveContact,
    saveProfile,
    selectChannel,
    simulation,
    step,
    token,
  };
}

type UseDiagnostic = ReturnType<typeof useDiagnostic>;

export { useDiagnostic };
export type { UseDiagnostic };

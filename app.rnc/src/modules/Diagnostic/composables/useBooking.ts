import { computed, ref, toValue } from 'vue';
import type { MaybeRefOrGetter } from 'vue';
import type { Availability, AvailabilitySpecialist, BookingConfirmation, BookMeetingPayload } from '../interfaces';
import type { ApiResponse } from '@Providers';
import { BOOKING_ERROR } from '../enums';
import { BookMeetingService, GetAvailabilityService } from '../services';

/** Enough to refuse a typo before the API does; the API's rule is the real one. */
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/** What the slot picker says for each refusal the API names (`BookingException`). */
const ERROR_KEYS: Record<string, string> = {
  [BOOKING_ERROR.ALREADY_BOOKED]: 'diagnostic.booking.errors.alreadyBooked',
  [BOOKING_ERROR.EMAIL_REQUIRED]: 'diagnostic.booking.errors.email',
  [BOOKING_ERROR.SLOT_TAKEN]: 'diagnostic.booking.errors.slotTaken',
  [BOOKING_ERROR.SLOT_UNAVAILABLE]: 'diagnostic.booking.errors.slotUnavailable',
};

/** The refusals after which the agenda on screen is stale and is read again. */
const RELOADING_ERRORS: string[] = [BOOKING_ERROR.SLOT_TAKEN, BOOKING_ERROR.SLOT_UNAVAILABLE];

interface BookingApiError {
  response?: { data?: { code?: string } };
}

/**
 * The slot picker's state (rnc-1 R15, R16): the agenda, the specialist (the one indicated for the
 * profile first), the day (its first with a free slot), the slot, and the booking. A slot somebody
 * took first is "acabou de ser reservado" and the agenda is read again.
 */
function useBooking(token: MaybeRefOrGetter<string>) {
  const availability = ref<Availability | null>(null);
  const chosenDate = ref<string | null>(null);
  const chosenSlot = ref<string | null>(null);
  const email = ref('');
  const errorKey = ref<string | null>(null);
  const isBooking = ref(false);
  const isLoading = ref(false);
  const specialistId = ref<number | null>(null);

  const specialist = computed(
    () => availability.value?.specialists.find((entry) => entry.id === specialistId.value) ?? null
  );

  const days = computed(() => specialist.value?.days ?? []);

  const slots = computed(() => days.value.find((day) => day.date === chosenDate.value)?.slots ?? []);

  // HELPERS
  function firstOpenDate(entry: AvailabilitySpecialist): string | null {
    return entry.days.find((day) => day.slots.length > 0)?.date ?? entry.days[0]?.date ?? null;
  }

  function select(id: number | null) {
    const entry = availability.value?.specialists.find((candidate) => candidate.id === id) ?? null;

    specialistId.value = entry?.id ?? null;
    chosenSlot.value = null;
    chosenDate.value = entry ? firstOpenDate(entry) : null;
  }

  function onAvailabilityLoaded({ data }: ApiResponse<Availability>) {
    const keeps = data.specialists.some((entry) => entry.id === specialistId.value);

    availability.value = data;

    select(keeps ? specialistId.value : (data.suggested_specialist_id ?? data.specialists[0]?.id ?? null));
  }

  function onBookingFailed(error: BookingApiError): null {
    const code = error.response?.data?.code ?? '';

    errorKey.value = ERROR_KEYS[code] ?? 'diagnostic.booking.errors.unavailable';

    if (RELOADING_ERRORS.includes(code)) load();

    return null;
  }

  // EVENTS
  function load() {
    isLoading.value = true;

    GetAvailabilityService(toValue(token))
      .then(onAvailabilityLoaded)
      .catch(() => (errorKey.value = 'diagnostic.booking.errors.unavailable'))
      .finally(() => (isLoading.value = false));
  }

  function chooseSpecialist(id: number) {
    errorKey.value = null;

    select(id);
  }

  function chooseDay(date: string) {
    errorKey.value = null;
    chosenDate.value = date;
    chosenSlot.value = null;
  }

  function chooseSlot(slot: string) {
    errorKey.value = null;
    chosenSlot.value = slot;
  }

  /** The booking; null when it was refused (the reason is in `errorKey`). */
  function book(needsEmail: boolean): Promise<BookingConfirmation | null> {
    const typedEmail = email.value.trim().toLowerCase();
    const isEmailMissing = needsEmail && !EMAIL_PATTERN.test(typedEmail);

    if (!specialistId.value || !chosenSlot.value) {
      errorKey.value = 'diagnostic.booking.errors.chooseSlot';
      return Promise.resolve(null);
    }

    if (isEmailMissing) {
      errorKey.value = 'diagnostic.booking.errors.email';
      return Promise.resolve(null);
    }

    const PAYLOAD: BookMeetingPayload = {
      specialist_id: specialistId.value,
      starts_at: chosenSlot.value,
      ...(needsEmail ? { email: typedEmail } : {}),
    };

    errorKey.value = null;
    isBooking.value = true;

    return BookMeetingService(toValue(token), PAYLOAD)
      .then(({ data }) => data)
      .catch(onBookingFailed)
      .finally(() => (isBooking.value = false));
  }

  return {
    availability,
    book,
    chooseDay,
    chooseSlot,
    chooseSpecialist,
    chosenDate,
    chosenSlot,
    days,
    email,
    errorKey,
    isBooking,
    isLoading,
    load,
    slots,
    specialist,
    specialistId,
  };
}

export { useBooking };

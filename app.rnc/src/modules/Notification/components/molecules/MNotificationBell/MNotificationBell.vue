<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue';
import { useI18n } from 'vue-i18n';
import type { BellNotification } from '../../../interfaces';
import { formatReal } from '@Helpers';
import { NOTIFICATION_POLL_MS } from '../../../constants';
import { NOTIFICATION_KIND } from '../../../enums';
import { useNotificationStore } from '../../../store';

/*
 * The back office's bell (rnc-1 R17, D5): the unread count, asked again every 30 s, and a dropdown
 * with one sentence per notification — a lead that left its contact, a meeting booked — each with
 * "marcar como lida", and "marcar todas". Meeting times are São Paulo's (AM9). A partner's move
 * (rnc-2 R9: aceitou, recusou, fechou contrato) reads its title and sentence by kind AND status —
 * one `referral_status` kind, three lines. The Reforma assistant's question, answer and
 * expiring review (rnc-3 F5) are three more kinds; the review's last day is São Paulo's too.
 */
const notificationStore = useNotificationStore();

const { locale, t } = useI18n();

const MAX_SHOWN_COUNT = 9;
const MEETING_TIME_ZONE = 'America/Sao_Paulo';
const KIND_ICONS: Record<string, string> = {
  [NOTIFICATION_KIND.ASSISTANT_ANSWER]: 'pi pi-comment',
  [NOTIFICATION_KIND.ASSISTANT_NOTE_EXPIRING]: 'pi pi-clock',
  [NOTIFICATION_KIND.ASSISTANT_QUESTION]: 'pi pi-question-circle',
  [NOTIFICATION_KIND.MEETING_BOOKED]: 'pi pi-calendar',
  [NOTIFICATION_KIND.NEW_LEAD]: 'pi pi-user-plus',
  [`${NOTIFICATION_KIND.REFERRAL_STATUS}_aceito`]: 'pi pi-check-circle',
  [`${NOTIFICATION_KIND.REFERRAL_STATUS}_recusado`]: 'pi pi-times-circle',
  [`${NOTIFICATION_KIND.REFERRAL_STATUS}_fechado`]: 'pi pi-briefcase',
};

const hasFailed = ref(false);
const panel = useTemplateRef<{ toggle: (event: Event) => void }>('panel');

const countLabel = computed(() =>
  notificationStore.unreadCount > MAX_SHOWN_COUNT ? `${MAX_SHOWN_COUNT}+` : String(notificationStore.unreadCount)
);

let pollTimer: ReturnType<typeof setInterval> | null = null;

onMounted(onComponentMount);
onBeforeUnmount(onComponentUnmount);

// HELPERS
function formatMoment(iso: string, options: Intl.DateTimeFormatOptions): string {
  return new Intl.DateTimeFormat(locale.value, { timeZone: MEETING_TIME_ZONE, ...options }).format(new Date(iso));
}

function sentenceOf(notification: BellNotification): string {
  const company = notification.company || t('notifications.unknownCompany');
  const when = notification.starts_at
    ? formatMoment(notification.starts_at, {
        day: '2-digit',
        hour: '2-digit',
        hourCycle: 'h23',
        minute: '2-digit',
        month: '2-digit',
        weekday: 'short',
      })
    : '';

  return t(`notifications.kinds.${lineOf(notification)}`, {
    answeredBy: notification.answered_by ?? '',
    askedBy: notification.asked_by ?? '',
    company,
    expiresOn: notification.expires_on ? formatDay(notification.expires_on) : '',
    partner: notification.partner ?? '',
    question: notification.question ?? '',
    specialist: notification.specialist ?? '',
    value: notification.deal_value_cents ? formatReal(notification.deal_value_cents) : '',
    when,
  });
}

/** A São Paulo day as the API sends it (`YYYY-MM-DD`), written dd/mm/yyyy without a time-zone shift. */
function formatDay(day: string): string {
  const [year, month, date] = day.split('-');

  return `${date}/${month}/${year}`;
}

/** The key a notification's title, sentence and icon are read by: its kind, and a partner move's status. */
function lineOf(notification: BellNotification): string {
  return notification.kind === NOTIFICATION_KIND.REFERRAL_STATUS
    ? `${notification.kind}_${notification.status}`
    : notification.kind;
}

function arrivedAt(notification: BellNotification): string {
  return formatMoment(notification.created_at, {
    day: '2-digit',
    hour: '2-digit',
    hourCycle: 'h23',
    minute: '2-digit',
    month: '2-digit',
  });
}

function refresh() {
  notificationStore
    .load()
    .then(() => (hasFailed.value = false))
    .catch(() => (hasFailed.value = true));
}

// EVENTS
function onComponentMount() {
  refresh();

  pollTimer = setInterval(refresh, NOTIFICATION_POLL_MS);
}

function onComponentUnmount() {
  if (pollTimer) clearInterval(pollTimer);
}

function onToggle(event: Event) {
  panel.value?.toggle(event);
}

function onMarkRead(id: string) {
  notificationStore.markRead(id).catch(() => (hasFailed.value = true));
}

function onMarkAllRead() {
  notificationStore.markAllRead().catch(() => (hasFailed.value = true));
}
</script>

<template>
  <div class="relative inline-flex shrink-0">
    <Button
      rounded
      text
      data-testid="bell"
      icon="pi pi-bell"
      severity="secondary"
      :aria-label="
        notificationStore.unreadCount > 0
          ? $t('notifications.bellUnread', { count: notificationStore.unreadCount })
          : $t('notifications.bell')
      "
      @click="onToggle"
    />

    <span
      v-if="notificationStore.unreadCount > 0"
      aria-hidden="true"
      class="bg-danger-600 ring-canvas text-danger-50 pointer-events-none absolute -top-0.5 -right-0.5 flex h-5 min-w-5 items-center justify-center rounded-full px-1 text-[0.7rem] leading-none font-bold tabular-nums ring-2"
      data-testid="bell-count"
    >
      {{ countLabel }}
    </span>

    <Popover ref="panel">
      <div
        class="flex w-[min(22rem,calc(100vw-2.5rem))] flex-col gap-3"
        data-testid="bell-panel"
      >
        <div class="flex items-center justify-between gap-3">
          <strong class="text-heading">{{ $t('notifications.title') }}</strong>

          <button
            v-if="notificationStore.unreadCount > 0"
            class="touch-target text-primary-700 hover:text-primary-800 text-sm font-semibold underline-offset-4 hover:underline"
            data-testid="bell-read-all"
            type="button"
            @click="onMarkAllRead"
          >
            {{ $t('notifications.markAllRead') }}
          </button>
        </div>

        <ul
          v-if="notificationStore.items.length > 0"
          class="-mx-2 flex max-h-[min(24rem,60vh)] flex-col overflow-y-auto"
        >
          <li
            v-for="notification in notificationStore.items"
            data-testid="bell-item"
            :key="notification.id"
            :class="[
              'flex items-start gap-3 rounded-md px-2 py-3',
              notification.read_at === null ? 'bg-primary-50' : '',
            ]"
            :data-read="notification.read_at !== null"
          >
            <i
              aria-hidden="true"
              :class="[KIND_ICONS[lineOf(notification)] ?? 'pi pi-bell', 'text-primary-700 mt-0.5']"
            />

            <div class="flex min-w-0 flex-1 flex-col gap-0.5">
              <span class="text-heading text-sm font-semibold">
                {{ $t(`notifications.kindTitles.${lineOf(notification)}`) }}
              </span>

              <span class="text-body text-sm break-words">{{ sentenceOf(notification) }}</span>

              <span class="text-muted text-xs tabular-nums">{{ arrivedAt(notification) }}</span>
            </div>

            <Button
              v-if="notification.read_at === null"
              rounded
              text
              data-testid="bell-read"
              icon="pi pi-check"
              severity="secondary"
              size="small"
              :aria-label="$t('notifications.markRead')"
              @click="onMarkRead(notification.id)"
            />
          </li>
        </ul>

        <p
          v-else
          class="text-muted text-sm"
        >
          {{ $t('notifications.empty') }}
        </p>

        <p
          v-if="hasFailed"
          class="text-muted text-xs"
          role="status"
        >
          {{ $t('errors.somethingWentWrong') }}
        </p>
      </div>
    </Popover>
  </div>
</template>

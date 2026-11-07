<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import type { PartnerMatch, PartnerMatches, ReferralTimelineEvent, ReferralWritten } from '../../../interfaces';
import { COVERED_SCORE } from '../../../constants';
import { GetPartnerMatchesService } from '../../../services';

/*
 * The routing panel on the lead detail (rnc-1 R22, the demo's team view): each active partner with
 * its score against this lead's diagnostic ("92% · ERP/NF-e") and the items it covers, best first,
 * and "Encaminhar" with a message. Franco stays the lead's owner. Without the lead's consent every
 * button is disabled and the reason sits above them — the API refuses it too (422). Whether the
 * person may refer at all is the VIEW's permission, passed in (celer-07).
 */
const emit = defineEmits<{
  onReferred: [event: ReferralTimelineEvent];
}>();

const props = defineProps<{
  canRefer: boolean;
  company: string | null;
  leadId: number;
}>();

const BLOCKED_REASON_ID = `routing-blocked-${props.leadId}`;
const TOAST_LIFE_MS = 3500;

const { t } = useI18n();
const toast = useToast();

const answer = ref<PartnerMatches | null>(null);
const hasFailed = ref(false);
const isLoading = ref(true);
const openPartnerId = ref<number | null>(null);

const isBlocked = computed(() => answer.value !== null && !answer.value.can_refer);
const matches = computed(() => answer.value?.matches ?? []);

onMounted(load);

// HELPERS
function load() {
  isLoading.value = true;
  hasFailed.value = false;

  GetPartnerMatchesService(props.leadId)
    .then((response) => (answer.value = response.data))
    .catch(() => (hasFailed.value = true))
    .finally(() => (isLoading.value = false));
}

function isCovering(match: PartnerMatch): boolean {
  return match.score >= COVERED_SCORE;
}

function sentOn(at: string): string {
  return new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: '2-digit' }).format(new Date(at));
}

// EVENTS
function onOpen(partnerId: number) {
  openPartnerId.value = partnerId;
}

function onReferred(match: PartnerMatch, written: ReferralWritten) {
  match.referral = { at: written.referral.at, id: written.referral.id, status: written.referral.status };
  openPartnerId.value = null;

  emit('onReferred', written.event);

  toast.add({
    life: TOAST_LIFE_MS,
    severity: 'success',
    summary: t('partners.routing.sent', { partner: match.partner.name }),
  });
}
</script>

<template>
  <section
    class="bg-panel shadow-lift flex flex-col gap-4 rounded-lg p-5"
    data-testid="lead-routing"
  >
    <div class="flex flex-col gap-1">
      <h3 class="text-heading text-lg font-semibold">{{ $t('partners.routing.title') }}</h3>

      <p class="text-muted text-sm">{{ $t('partners.routing.lede') }}</p>
    </div>

    <div
      v-if="isLoading && !answer"
      class="flex flex-col gap-3"
    >
      <Skeleton height="4rem" />

      <Skeleton height="4rem" />
    </div>

    <div
      v-else-if="hasFailed"
      class="flex flex-col items-start gap-3"
      role="alert"
    >
      <p class="text-body text-sm">{{ $t('partners.routing.loadFailed') }}</p>

      <Button
        icon="pi pi-refresh"
        severity="secondary"
        size="small"
        :label="$t('partners.retry')"
        @click="load"
      />
    </div>

    <template v-else-if="answer">
      <p
        v-if="isBlocked && answer.blocked_reason"
        :id="BLOCKED_REASON_ID"
        class="bg-warn-50 text-warn-900 flex items-start gap-2 rounded-md px-3 py-2 text-sm"
        data-testid="routing-blocked"
      >
        <i
          aria-hidden="true"
          class="pi pi-lock mt-0.5"
        />

        <span>{{ $t(`partners.routing.blocked.${answer.blocked_reason}`) }}</span>
      </p>

      <p
        v-if="matches.length === 0"
        class="text-muted text-sm"
        data-testid="routing-empty"
      >
        {{ $t('partners.routing.empty') }}
      </p>

      <ul
        v-else
        class="divide-line flex flex-col divide-y"
      >
        <li
          v-for="match in matches"
          class="flex flex-col gap-3 py-4 first:pt-0 last:pb-0"
          data-testid="partner-match"
          :key="match.partner.id"
          :data-partner="match.partner.id"
        >
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex min-w-0 flex-col gap-1.5">
              <span
                data-testid="match-score"
                :class="[
                  'w-fit rounded-sm px-2 py-0.5 text-xs font-semibold tabular-nums',
                  isCovering(match) ? 'bg-primary-100 text-primary-950' : 'bg-panel-muted text-muted',
                ]"
              >
                {{
                  $t('partners.routing.score', {
                    score: match.score,
                    specialty: $t(`partners.specialties.${match.partner.specialty}`),
                  })
                }}
              </span>

              <span class="text-heading font-medium break-words">{{ match.partner.name }}</span>

              <span
                v-if="match.partner.city"
                class="text-muted text-xs"
              >
                {{ match.partner.city }}
              </span>
            </div>

            <span
              v-if="match.referral"
              class="bg-success-50 text-success-800 flex w-fit shrink-0 items-center gap-1.5 rounded-sm px-2 py-1 text-xs font-semibold"
              data-testid="match-referred"
            >
              <i
                aria-hidden="true"
                class="pi pi-check"
              />

              {{ $t('partners.routing.referredOn', { date: sentOn(match.referral.at) }) }}
            </span>

            <Button
              v-else-if="props.canRefer && openPartnerId !== match.partner.id"
              class="shrink-0"
              data-testid="refer-open"
              icon="pi pi-share-alt"
              severity="secondary"
              size="small"
              :aria-describedby="isBlocked ? BLOCKED_REASON_ID : undefined"
              :disabled="isBlocked"
              :label="$t('partners.routing.refer')"
              @click="onOpen(match.partner.id)"
            />
          </div>

          <ul
            v-if="match.covers.length > 0"
            class="flex flex-wrap gap-2"
            :aria-label="$t('partners.routing.coversLabel')"
          >
            <li
              v-for="item in match.covers"
              class="border-line text-body rounded-sm border px-2 py-0.5 text-xs"
              :key="item.id"
            >
              {{ item.title }}
            </li>
          </ul>

          <p
            v-else
            class="text-muted text-xs"
          >
            {{ $t('partners.routing.coversNothing') }}
          </p>

          <MReferralForm
            v-if="openPartnerId === match.partner.id && !isBlocked"
            :company="props.company"
            :leadId="props.leadId"
            :partnerId="match.partner.id"
            :partnerName="match.partner.name"
            @onClose="openPartnerId = null"
            @onSuccess="(written: ReferralWritten) => onReferred(match, written)"
          />
        </li>
      </ul>
    </template>
  </section>
</template>

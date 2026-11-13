import { useI18n } from 'vue-i18n';
import type { LeadTimelineEvent } from '../interfaces';
import { MEETING_TIME_ZONE } from '@DiagnosticModule';
import { TIMELINE_EVENT_KEYS } from '../constants';
import { LEAD_EVENT_TYPE, LEAD_STAGE } from '../enums';

const MEETING_FORMAT: Intl.DateTimeFormatOptions = {
  day: '2-digit',
  hour: '2-digit',
  hourCycle: 'h23',
  minute: '2-digit',
  month: '2-digit',
  weekday: 'short',
};

const MOMENT_FORMAT: Intl.DateTimeFormatOptions = {
  day: '2-digit',
  hour: '2-digit',
  hourCycle: 'h23',
  minute: '2-digit',
  month: '2-digit',
};

const DATE_FORMAT: Intl.DateTimeFormatOptions = { day: '2-digit', month: '2-digit', year: 'numeric' };

const TIME_FORMAT: Intl.DateTimeFormatOptions = { hour: '2-digit', hourCycle: 'h23', minute: '2-digit' };

/**
 * The pipeline's words for the API's values: stage names, times in São Paulo (AM9), and one sentence
 * per timeline line — the API stores the facts, the SPA writes the sentence.
 */
function useLeadFormat() {
  const { locale, t } = useI18n();

  // HELPERS
  function format(iso: string, options: Intl.DateTimeFormatOptions): string {
    return new Intl.DateTimeFormat(locale.value, { timeZone: MEETING_TIME_ZONE, ...options }).format(new Date(iso));
  }

  function formatDate(iso: string): string {
    return format(iso, DATE_FORMAT);
  }

  function formatMeeting(iso: string): string {
    return format(iso, MEETING_FORMAT);
  }

  function formatMoment(iso: string): string {
    return format(iso, MOMENT_FORMAT);
  }

  function formatTime(iso: string): string {
    return format(iso, TIME_FORMAT);
  }

  function stageLabel(stage: string): string {
    return t(`pipeline.stages.${stage}`);
  }

  function textOf(value: unknown): string {
    return typeof value === 'string' || typeof value === 'number' ? String(value) : '';
  }

  function sentenceOf(event: LeadTimelineEvent): string {
    const { data } = event;
    const name = TIMELINE_EVENT_KEYS[event.type as LEAD_EVENT_TYPE];

    if (!name) return t('pipeline.lead.timeline.events.unknown', { type: event.type });

    const key = `pipeline.lead.timeline.events.${name}`;

    if (event.type === LEAD_EVENT_TYPE.STAGE_CHANGED) return stageChangeSentence(data);

    if (event.type === LEAD_EVENT_TYPE.DIAGNOSTIC_STARTED) {
      return t(key, { path: t(`pipeline.lead.profile.paths.${textOf(data.path) || 'quiz'}`) });
    }

    if (event.type === LEAD_EVENT_TYPE.REFERRAL_SENT)
      return t(key, { partner: textOf(data.partner), score: Number(data.score ?? 0) });

    if (event.type === LEAD_EVENT_TYPE.STEP_REACHED) return t(key, { step: t(`pipeline.steps.${textOf(data.step)}`) });

    if (event.type === LEAD_EVENT_TYPE.MEETING_BOOKED) {
      const startsAt = textOf(data.starts_at);

      return t(key, { specialist: textOf(data.specialist), when: startsAt ? formatMeeting(startsAt) : '' });
    }

    if (event.type === LEAD_EVENT_TYPE.RESULT_SEEN) {
      return t(key, {
        opportunities: t('diagnostic.result.opportunities', Number(data.opportunities ?? 0)),
        risks: t('diagnostic.result.risks', Number(data.risks ?? 0)),
      });
    }

    return t(key, { text: textOf(data.text) });
  }

  function stageChangeSentence(data: Record<string, unknown>): string {
    const isLost = data.to === LEAD_STAGE.PERDIDO && Boolean(data.reason);
    const via = textOf(data.via) ? ` (${t(`pipeline.lead.timeline.vias.${textOf(data.via)}`)})` : '';
    const moved = t(`pipeline.lead.timeline.events.${isLost ? 'stageLost' : 'stageChanged'}`, {
      from: stageLabel(textOf(data.from)),
      reason: textOf(data.reason),
      to: stageLabel(textOf(data.to)),
    });

    return `${moved}${via}`;
  }

  return { formatDate, formatMeeting, formatMoment, formatTime, sentenceOf, stageLabel };
}

export { useLeadFormat };

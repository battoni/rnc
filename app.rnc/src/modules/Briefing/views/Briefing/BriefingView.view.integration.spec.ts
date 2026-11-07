import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import { createPinia, setActivePinia } from 'pinia';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { Briefing } from '../../interfaces';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { MSlaChip } from '@PortalModule';
import { useUserStore } from '@UserModule';
import { MCountdown } from '../../components';
import { BRIEFING_FIXTURE, PARTIAL_LEADS_ACTION, QUIET_BRIEFING, RADAR_ACTION } from '../../test/fixtures';
import BriefingView from './BriefingView.view.vue';

/*
 * "Briefing do dia" (rnc-3 F9, R26, A23) under a fixed clock: the greeting by the São Paulo hour,
 * the four KPIs as the API counted them, "As 3 ações de hoje" in the API's order — each sentence
 * written here from the numbers (W82) and a button that opens the screen the action names (the
 * trail by `?parceiro=`, the pipeline by `?etapa=`, W83) —, the countdown to 01/01/2027, and the
 * three panels: meetings, referrals by SLA ("and N more") and the pipeline by stage.
 */
const navigated = vi.hoisted(() => [] as unknown[]);
const answers: { briefing: Briefing; fails: boolean } = { briefing: BRIEFING_FIXTURE, fails: false };
let requests = 0;

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: {}, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}));

const STUBS = {
  RouterLink: {
    props: { custom: Boolean, to: { required: true, type: [String, Object] } },
    setup(props: Record<string, unknown>) {
      return {
        navigate: () => {
          navigated.push(props.to);
        },
      };
    },
    template:
      '<slot v-if="custom" :navigate="navigate" /><a v-else data-testid="link" :data-to="JSON.stringify(to)"><slot /></a>',
  },
};

function renderBriefing() {
  const pinia = createPinia();
  setActivePinia(pinia);
  useUserStore().setUserAndPermissions(
    { id: 2, last_name: 'Franco', name: 'Reinaldo', role: 'CEO', role_slug: 'ceo', username: 'reinaldo' },
    ['leads.read', 'briefing.read']
  );

  return renderWithPlugins(BriefingView, {
    global: { components: { Button, MCountdown, MSlaChip, Skeleton }, stubs: STUBS },
    pinia,
  });
}

/** The clock at a São Paulo wall time (UTC−3); only `Date` is faked so the API mock still answers. */
function atSaoPaulo(localIso: string) {
  vi.useFakeTimers({ toFake: ['Date'] });
  vi.setSystemTime(new Date(`${localIso}-03:00`));
}

function plain(text: string | null | undefined): string {
  return (text ?? '').replace(/\s+/g, ' ').trim();
}

/** A KPI tile's label, figure and sub-line, each as it reads. */
function figuresOf(id: string): string[] {
  return Array.from(kpi(id).children).map((part) => plain(part.textContent));
}

function kpi(id: string): HTMLElement {
  return screen.getAllByTestId('briefing-kpi').find((tile) => tile.dataset.kpi === id)!;
}

beforeEach(() => {
  navigated.length = 0;
  requests = 0;
  answers.briefing = BRIEFING_FIXTURE;
  answers.fails = false;
  server.use(
    http.get('http://localhost/briefing', () => {
      requests++;

      return answers.fails
        ? HttpResponse.json({ error: 'errors.unexpected' }, { status: 500 })
        : HttpResponse.json({ data: structuredClone(answers.briefing) });
    })
  );
});

afterEach(() => {
  vi.useRealTimers();
});

describe('BriefingView', () => {
  it.each([
    ['2026-11-09T09:15:00', 'Good morning, Reinaldo'],
    ['2026-11-09T05:00:00', 'Good morning, Reinaldo'],
    ['2026-11-09T12:00:00', 'Good afternoon, Reinaldo'],
    ['2026-11-09T17:59:00', 'Good afternoon, Reinaldo'],
    ['2026-11-09T18:00:00', 'Good evening, Reinaldo'],
    ['2026-11-09T04:59:00', 'Good evening, Reinaldo'],
  ])(
    'greets by the São Paulo hour: %s → "%s", from a browser in another zone too (A23)',
    async (localIso, greeting) => {
      const zone = process.env.TZ;

      process.env.TZ = 'Asia/Tokyo';
      atSaoPaulo(localIso);

      try {
        renderBriefing();

        expect(plain(screen.getByTestId('briefing-greeting').textContent)).toBe(greeting);
        expect(screen.getByText(/Briefing for Monday/)).toBeTruthy();
      } finally {
        process.env.TZ = zone;
      }
    }
  );

  it('shows the four KPIs as the API counted them (A23)', async () => {
    atSaoPaulo('2026-11-09T09:15:00');
    renderBriefing();

    await screen.findAllByTestId('briefing-kpi');

    expect(figuresOf('leads')).toEqual(['New leads · 7 days', '14', '2 in the last 24h']);
    expect(figuresOf('meetings')).toEqual(['Meetings · next 5 business days', '6', '2 today']);
    expect(figuresOf('referrals')).toEqual(['Referrals awaiting contact', '7', '2 past the SLA · 1 at risk']);
    expect(plain(kpi('pipeline').textContent)).toContain('R$ 120 mil – R$ 216 mil');
    expect(plain(kpi('pipeline').textContent)).toContain('9 deals · estimated annual fee (portfolio range)');
  });

  it("lists today's actions in the API's order, each sentence from its numbers (A23, R25)", async () => {
    atSaoPaulo('2026-11-09T09:15:00');
    renderBriefing();

    const actions = await screen.findAllByTestId('briefing-action');

    expect(actions.map((action) => action.dataset.kind)).toEqual(['sla_breached', 'meetings_today', 'proposals']);
    expect(plain(actions[0]!.textContent)).toContain(
      'Chase Margem Certa (exemplo): Padaria Trigo Dourado has waited 31.5 business hours for a first contact (target 24h).'
    );
    expect(plain(actions[1]!.textContent)).toContain("Prepare today's 2 meetings with the specialists.");
    expect(plain(actions[2]!.textContent)).toContain('Close 3 open proposals: R$ 54 mil – R$ 90 mil/year in fees.');
  });

  it('gives each action a button to the screen it names, filtered (A23, W83)', async () => {
    atSaoPaulo('2026-11-09T09:15:00');
    renderBriefing();

    const buttons = await screen.findAllByTestId('briefing-action-go');

    expect(buttons.map((button) => plain(button.textContent))).toEqual(['See partner', 'Open agenda', 'See pipeline']);
    expect(buttons.map((button) => button.dataset.to)).toEqual([
      '/reinaldo/trilha?parceiro=7',
      '/equipe/agenda',
      '/equipe/pipeline?etapa=proposta',
    ]);

    await fireEvent.click(buttons[0]!);
    await fireEvent.click(buttons[2]!);

    expect(navigated).toEqual(['/reinaldo/trilha?parceiro=7', '/equipe/pipeline?etapa=proposta']);
  });

  it('writes the partial-lead and the monthly Radar sentences, and a proposal without a base', async () => {
    atSaoPaulo('2026-11-09T09:15:00');
    answers.briefing = {
      ...BRIEFING_FIXTURE,
      actions: [
        {
          kind: BRIEFING_FIXTURE.actions[2]!.kind,
          params: { count: 1, range_cents: null },
          to: '/equipe/pipeline?etapa=proposta',
        },
        PARTIAL_LEADS_ACTION,
        RADAR_ACTION,
      ] as Briefing['actions'],
    };
    renderBriefing();

    const actions = await screen.findAllByTestId('briefing-action');

    expect(actions.map((action) => plain(within(action).getByTestId('briefing-action-text').textContent))).toEqual([
      '1 Close 1 open proposal.',
      '2 Call 1 partial lead: left a contact and stopped mid-diagnostic.',
      '3 Radar: 8 portfolio clients with “Split payment no caixa”, R$ 40 mil – R$ 65 mil/month.',
    ]);
    expect(actions.map((action) => plain(within(action).getByTestId('briefing-action-go').textContent))).toEqual([
      'See pipeline',
      'Call list',
      'Open Radar',
    ]);
  });

  it('counts down to 01/01/2027 00:00 in São Paulo under the fixed clock (A23)', async () => {
    atSaoPaulo('2026-11-09T09:15:00');
    renderBriefing();

    const countdown = await screen.findByTestId('briefing-countdown');
    const parts = Object.fromEntries(
      Array.from(countdown.querySelectorAll('[data-part]')).map((part) => [
        part.getAttribute('data-part'),
        part.querySelector('strong')?.textContent?.trim(),
      ])
    );

    expect(parts).toEqual({ days: '52', hours: '14', minutes: '45', seconds: '00' });
    expect(within(countdown).getByTestId('countdown-caption').textContent).toContain('read on 05/11/2026');
  });

  it('lists the next meetings with a link to each lead', async () => {
    atSaoPaulo('2026-11-09T09:15:00');
    renderBriefing();

    const meetings = await screen.findAllByTestId('briefing-meeting');

    expect(meetings).toHaveLength(2);
    expect(plain(meetings[0]!.textContent)).toContain('Centro Médico Bandeira Ltda');
    expect(plain(meetings[0]!.textContent)).toContain('Mariana');
    expect(plain(meetings[1]!.textContent)).toContain('Unnamed company');
    expect(JSON.parse(within(meetings[0]!).getByTestId('link').dataset.to!)).toMatchObject({ params: { id: 11 } });
    expect(plain(screen.getByTestId('briefing-meetings').textContent)).toContain('and 4 more.');
  });

  it('lists the referrals by SLA with their chips, and how many more', async () => {
    atSaoPaulo('2026-11-09T09:15:00');
    renderBriefing();

    const referrals = await screen.findAllByTestId('briefing-referral');

    expect(referrals).toHaveLength(5);
    expect(plain(referrals[0]!.textContent)).toContain('Padaria Trigo Dourado → Margem Certa (exemplo)');
    expect(referrals.map((row) => within(row).getByTestId('sla-chip').dataset.sla)).toEqual([
      'estourado',
      'estourado',
      'atencao',
      'ok',
      'ok',
    ]);
    expect(screen.getByText('Referrals and the 24 business-hour SLA')).toBeTruthy();
    expect(plain(screen.getByTestId('briefing-referrals-more').textContent)).toBe('and 2 more.');
  });

  it('shows the pipeline by stage with its count and annual range', async () => {
    atSaoPaulo('2026-11-09T09:15:00');
    renderBriefing();

    const stages = await screen.findAllByTestId('briefing-stage');

    expect(stages.map((stage) => stage.dataset.stage)).toEqual(['lead', 'reuniao_marcada', 'proposta']);
    expect(plain(stages[2]!.textContent)).toContain('3 deals · R$ 54 mil – R$ 90 mil/year');
  });

  it('says a quiet day has nothing urgent, and an empty portfolio has no base', async () => {
    atSaoPaulo('2026-11-09T09:15:00');
    answers.briefing = QUIET_BRIEFING;
    renderBriefing();

    expect(await screen.findByTestId('briefing-actions-empty')).toBeTruthy();
    expect(screen.queryAllByTestId('briefing-action')).toHaveLength(0);
    expect(plain(kpi('pipeline').textContent)).toContain('no portfolio base');
    expect(plain(screen.getByTestId('briefing-meetings').textContent)).toContain(
      'No meetings in the next 5 business days.'
    );
    expect(plain(screen.getByTestId('briefing-referrals').textContent)).toContain(
      'No referral awaiting a first contact.'
    );
    expect(screen.queryByTestId('briefing-referrals-more')).toBeNull();
  });

  it('says the briefing did not load, and reads it again on retry', async () => {
    atSaoPaulo('2026-11-09T09:15:00');
    answers.fails = true;
    renderBriefing();

    await screen.findByTestId('briefing-failed');
    answers.fails = false;
    await fireEvent.click(screen.getByRole('button', { name: 'Try again' }));

    await waitFor(() => expect(screen.getAllByTestId('briefing-kpi')).toHaveLength(4));
    expect(requests).toBe(2);
  });
});

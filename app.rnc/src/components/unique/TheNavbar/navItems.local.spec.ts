import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';
import type { NavItem } from './interfaces';
import i18n from '@Libraries/i18n';
import { useUserStore } from '@UserModule';
import { useLocalNavItems } from './navItems.local';

/*
 * The hub's rail (rnc-1 R3): Equipe · Reinaldo · Operador, one entry per group, each closed by the
 * permission that only its audience holds — TheNavbar's `isVisibleNavItem` does the hiding, and
 * TheNavbar.spec proves that gate. What this proves is that every group declares one.
 */
const ALL_MODULES = [
  'portfolio',
  'fiscal',
  'radar',
  'partners',
  'contracts',
  'chat',
  'assistant',
  'briefing',
  'brands',
  'channel',
];

function signInWithModules(modules: string[], isSpecialist = false) {
  useUserStore().setUserAndPermissions(
    {
      firm: { modules, name: 'Alvorada Contabilidade', slug: 'alvorada' },
      id: 7,
      is_specialist: isSpecialist,
      last_name: 'Alvorada',
      name: 'Helena',
      role: 'Gestão',
      role_slug: 'gestao',
      username: 'helena.alvorada',
    } as never,
    []
  );
}

/** Every route name the rail offers, children included, in order. */
function routeNamesOf(items: NavItem[]): string[] {
  return items.flatMap((item) => [
    item.routeName ?? '',
    ...(item.children ?? []).map((child) => child.routeName ?? ''),
  ]);
}

describe('useLocalNavItems', () => {
  /*
   * The declaration tests below read the rail of a firm with EVERY module on (Franco's): which entry
   * a module hides is the rnc-4 block at the end.
   */
  beforeEach(() => {
    setActivePinia(createPinia());
    signInWithModules(ALL_MODULES);
  });

  it('declares the three groups and the portal, in order, each with its gate', () => {
    const items = useLocalNavItems().value;

    expect(items.map((item) => [item.label, item.routeName, item.permission, item.role])).toEqual([
      ['navigation.team', 'team-home', 'leads.read', undefined],
      ['navigation.management', 'management-home', 'metrics.read', undefined],
      ['navigation.operator', 'operator-home', 'users.read', undefined],
      // rnc-2 A4: the portal for a partner's login only — the key AND the role, so the operator,
      // who holds every key, never sees it, and a partner holds none of the three groups' keys.
      ['navigation.portal', 'portal', 'portal.access', 'parceiro'],
      // rnc-4 R24: the platform console, the firm-less operator's alone — no firm role holds the key (W53).
      ['navigation.platform', 'platform-firms', 'platform.firms', undefined],
    ]);
  });

  it('puts the firms and the default consent under "Plataforma", each behind its own key (rnc-4 R25)', () => {
    const platform = useLocalNavItems().value[4];

    expect(platform?.children?.map((child) => [child.label, child.routeName, child.permission])).toEqual([
      ['navigation.platformFirms', 'platform-firms', 'platform.firms'],
      ['navigation.platformConsent', 'platform-consent', 'platform.consent'],
    ]);
  });

  // A13 (rnc-4 R14): the manager's group is named for the role, never for a person.
  it('reads "Gestão" on the management group and its home card, in both languages', () => {
    const { t } = i18n.global;

    expect(t('navigation.management', {}, { locale: 'pt-BR' })).toBe('Gestão');
    expect(t('home.workspaces.management.title', {}, { locale: 'pt-BR' })).toBe('Gestão');
    expect(t('navigation.management', {}, { locale: 'en' })).toBe('Management');
    expect(t('home.workspaces.management.title', {}, { locale: 'en' })).toBe('Management');
  });

  it("puts the bootstrap's administration screens in the operator's group", () => {
    const operator = useLocalNavItems().value[2];

    expect(operator?.children?.map((child) => child.routeName)).toEqual([
      'users',
      'roles-and-permissions',
      'brands',
      'channel',
    ]);
  });

  it("puts the brands console in the operator's group, behind its own key (rnc-3 R5, D3)", () => {
    const brands = useLocalNavItems().value[2]?.children?.find((child) => child.routeName === 'brands');

    expect([brands?.label, brands?.permission]).toEqual(['navigation.brands', 'brands.manage']);
  });

  it("puts the team's pipeline, agenda, the Reforma assistant (rnc-3 R12) and the two fiscal screens in its group", () => {
    const team = useLocalNavItems().value[0];

    expect(team?.children?.map((child) => [child.label, child.routeName, child.permission])).toEqual([
      ['navigation.pipeline', 'pipeline', 'leads.read'],
      ['navigation.agenda', 'agenda', 'agenda.read'],
      ['navigation.assistant', 'assistant', 'assistant.ask'],
      // rnc-2 F8 (R18, R21): "Prontidão da NF-e" and "Crédito de fornecedores", behind `fiscal.read` (D14).
      ['navigation.readiness', 'nfe-readiness', 'fiscal.read'],
      ['navigation.suppliers', 'supplier-credit', 'fiscal.read'],
    ]);
  });

  it('shows "Base da Reforma" under Equipe only to a specialist or to assistant.manage (rnc-3 R10, AM4)', () => {
    const knowledgeFor = (isSpecialist: boolean, keys: string[]) => {
      useUserStore().setUserAndPermissions(
        {
          id: 9,
          is_specialist: isSpecialist,
          last_name: 'P',
          name: 'U',
          role: 'R',
          role_slug: 'equipe',
          username: 'u',
        },
        keys
      );

      return useLocalNavItems()
        .value[0]?.children?.filter((child) => child.label === 'navigation.knowledge')
        .map((child) => [child.routeName, child.permission]);
    };

    expect(knowledgeFor(true, ['assistant.ask'])).toEqual([['assistant-knowledge', 'assistant.ask']]);
    expect(knowledgeFor(false, ['assistant.ask', 'assistant.manage'])).toEqual([
      ['assistant-knowledge', 'assistant.ask'],
    ]);
    expect(knowledgeFor(false, ['assistant.ask'])).toEqual([]);
  });

  it("puts the daily briefing first, then the metrics, the partner registry, the trail, the network ledger, the repricing, the RNC channel, the XML import, the Radar and the contract alert in Reinaldo's group, each behind its own key (AM8)", () => {
    const ceo = useLocalNavItems().value[1];

    expect(ceo?.children?.map((child) => [child.routeName, child.permission])).toEqual([
      // rnc-3 F9 (R26): "Briefing do dia" — `briefing.read` (gestao), Reinaldo's landing.
      ['briefing', 'briefing.read'],
      ['metrics', 'metrics.read'],
      ['partners', 'partners.manage'],
      ['referral-trail', 'referrals.read'],
      // rnc-2 F4 (R11): "Extrato da rede", behind `ledger.read` (D14).
      ['network-ledger', 'ledger.read'],
      ['repricing', 'repricing.manage'],
      ['management-channel', 'channel.read'],
      // rnc-2 F8 (R15, R17): "Notas fiscais", the XML import — `fiscal.import` (gestao, D14).
      ['nfe-imports', 'fiscal.import'],
      // rnc-2 F10 (R24–R27): "Radar da carteira" — `radar.manage` (gestao, D14).
      ['portfolio-radar', 'radar.manage'],
      // rnc-3 F7 (R18–R22): "Alerta de contratos" — `contracts.manage` (gestao).
      ['contract-alert', 'contracts.manage'],
      // rnc-4 F5b (R30–R33): the firm's own settings — contact, team, specialists' hours, consent.
      ['firm-contact', 'firm.settings'],
      ['firm-team', 'firm.settings'],
      ['firm-specialists', 'firm.settings'],
      ['firm-consent', 'firm.settings'],
    ]);
  });

  it("puts the RNC channel in the operator's group and in Reinaldo's, both on the read key (rnc-3 R8, R9)", () => {
    const [, ceo, operator] = useLocalNavItems().value;
    const channelOf = (children: NavItem['children']) =>
      children
        ?.filter((child) => child.label === 'navigation.channel')
        .map((child) => [child.routeName, child.permission]);

    expect(channelOf(operator?.children)).toEqual([['channel', 'channel.read']]);
    expect(channelOf(ceo?.children)).toEqual([['management-channel', 'channel.read']]);
  });

  it("puts the partner's statement under its portal, behind the same key and role (rnc-2 R11)", () => {
    const portal = useLocalNavItems().value[3];

    expect(portal?.children?.map((child) => [child.label, child.routeName, child.permission, child.role])).toEqual([
      ['navigation.statement', 'portal-statement', 'portal.access', 'parceiro'],
    ]);
  });
  /*
   * rnc-4 A24 (R17, R20): a module the firm has off takes its screens out of the rail — the API
   * answers them 404 and the router shows not-found. Without `partners` no partner entry is left:
   * the registry, the trail, the ledger, and the partner's portal.
   */
  it('has no partner entry when the firm has `partners` off (rnc-4 A24)', () => {
    signInWithModules(ALL_MODULES.filter((module) => module !== 'partners'));

    const names = routeNamesOf(useLocalNavItems().value);

    expect(names).not.toContain('partners');
    expect(names).not.toContain('referral-trail');
    expect(names).not.toContain('network-ledger');
    expect(names).not.toContain('portal');
    expect(names).not.toContain('portal-statement');
    expect(names).toContain('metrics');
    expect(names).toContain('briefing');
  });

  it.each([
    ['portfolio', ['repricing']],
    ['fiscal', ['nfe-readiness', 'supplier-credit', 'nfe-imports']],
    ['radar', ['portfolio-radar']],
    ['contracts', ['contract-alert']],
    ['assistant', ['assistant', 'assistant-knowledge']],
    ['briefing', ['briefing']],
    ['brands', ['brands']],
    ['channel', ['management-channel', 'channel']],
  ])('drops the screens of `%s` when it is off, and only those', (module, screens) => {
    signInWithModules(ALL_MODULES, true);
    const everyScreen = routeNamesOf(useLocalNavItems().value);

    signInWithModules(
      ALL_MODULES.filter((key) => key !== module),
      true
    );

    expect(routeNamesOf(useLocalNavItems().value)).toEqual(everyScreen.filter((name) => !screens.includes(name)));
  });

  it('keeps only the core screens for a firm with no opt-in module (the platform firm)', () => {
    signInWithModules([]);

    expect(routeNamesOf(useLocalNavItems().value)).toEqual([
      'team-home',
      'pipeline',
      'agenda',
      'management-home',
      'metrics',
      'firm-contact',
      'firm-team',
      'firm-specialists',
      'firm-consent',
      'operator-home',
      'users',
      'roles-and-permissions',
      'platform-firms',
      'platform-firms',
      'platform-consent',
    ]);
  });
});

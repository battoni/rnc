import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';
import type { NavItem } from './interfaces';
import { useUserStore } from '@UserModule';
import { useLocalNavItems } from './navItems.local';

/*
 * The hub's rail (rnc-1 R3): Equipe · Reinaldo · Operador, one entry per group, each closed by the
 * permission that only its audience holds — TheNavbar's `isVisibleNavItem` does the hiding, and
 * TheNavbar.spec proves that gate. What this proves is that every group declares one.
 */
describe('useLocalNavItems', () => {
  beforeEach(() => setActivePinia(createPinia()));

  it('declares the three groups and the portal, in order, each with its gate', () => {
    const items = useLocalNavItems().value;

    expect(items.map((item) => [item.label, item.routeName, item.permission, item.role])).toEqual([
      ['navigation.team', 'team-home', 'leads.read', undefined],
      ['navigation.ceo', 'ceo-home', 'metrics.read', undefined],
      ['navigation.operator', 'operator-home', 'users.read', undefined],
      // rnc-2 A4: the portal for a partner's login only — the key AND the role, so the operator,
      // who holds every key, never sees it, and a partner holds none of the three groups' keys.
      ['navigation.portal', 'portal', 'portal.access', 'parceiro'],
    ]);
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

  it("puts the team's pipeline, agenda and the Reforma assistant in its group (rnc-3 R12)", () => {
    const team = useLocalNavItems().value[0];

    expect(team?.children?.map((child) => [child.routeName, child.permission])).toEqual([
      ['pipeline', 'leads.read'],
      ['agenda', 'agenda.read'],
      ['assistant', 'assistant.ask'],
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

  it("puts the metrics, the partner registry, the trail and the repricing in Reinaldo's group, each behind its own key (AM8)", () => {
    const ceo = useLocalNavItems().value[1];

    expect(ceo?.children?.map((child) => [child.routeName, child.permission])).toEqual([
      ['metrics', 'metrics.read'],
      ['partners', 'partners.manage'],
      ['referral-trail', 'referrals.read'],
      ['repricing', 'repricing.manage'],
      ['ceo-channel', 'channel.read'],
    ]);
  });

  it("puts the RNC channel in the operator's group and in Reinaldo's, both on the read key (rnc-3 R8, R9)", () => {
    const [, ceo, operator] = useLocalNavItems().value;
    const channelOf = (children: NavItem['children']) =>
      children
        ?.filter((child) => child.label === 'navigation.channel')
        .map((child) => [child.routeName, child.permission]);

    expect(channelOf(operator?.children)).toEqual([['channel', 'channel.read']]);
    expect(channelOf(ceo?.children)).toEqual([['ceo-channel', 'channel.read']]);
  });
});

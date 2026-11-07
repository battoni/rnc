import { describe, expect, it } from 'vitest';
import { useLocalNavItems } from './navItems.local';

/*
 * The hub's rail (rnc-1 R3): Equipe · Reinaldo · Operador, one entry per group, each closed by the
 * permission that only its audience holds — TheNavbar's `isVisibleNavItem` does the hiding, and
 * TheNavbar.spec proves that gate. What this proves is that every group declares one.
 */
describe('useLocalNavItems', () => {
  it('declares the three groups, in order, each with its gate', () => {
    const items = useLocalNavItems().value;

    expect(items.map((item) => [item.label, item.routeName, item.permission])).toEqual([
      ['navigation.team', 'team-home', 'leads.read'],
      ['navigation.ceo', 'ceo-home', 'metrics.read'],
      ['navigation.operator', 'operator-home', 'users.read'],
    ]);
  });

  it("puts the bootstrap's administration screens in the operator's group", () => {
    const operator = useLocalNavItems().value[2];

    expect(operator?.children?.map((child) => child.routeName)).toEqual(['users', 'roles-and-permissions']);
  });

  it("puts the team's pipeline and agenda in its group", () => {
    const team = useLocalNavItems().value[0];

    expect(team?.children?.map((child) => [child.routeName, child.permission])).toEqual([
      ['pipeline', 'leads.read'],
      ['agenda', 'agenda.read'],
    ]);
  });

  it("puts the partner registry and the trail in Reinaldo's group, each behind its own key (AM8)", () => {
    const ceo = useLocalNavItems().value[1];

    expect(ceo?.children?.map((child) => [child.routeName, child.permission])).toEqual([
      ['partners', 'partners.manage'],
      ['referral-trail', 'referrals.read'],
    ]);
  });
});

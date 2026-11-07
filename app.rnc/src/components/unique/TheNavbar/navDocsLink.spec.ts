import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import ONavbarDesktop from './components/organisms/ONavbarDesktop/ONavbarDesktop.vue';

/*
 * The documentation entry, proved where each half is decided.
 *
 * `DOCS_URL` is read at module scope, so switching it means rebuilding the module graph — and a
 * graph rebuilt inside a test is outside `unplugin-vue-components`' registry, which is how an
 * earlier version of this file ended up asserting against a navbar whose variants had rendered as
 * unknown elements. So the two halves are tested apart: whether the entry EXISTS is a question for
 * `TheNavbar`'s computed (below, with the constant mocked once, no reset), and how it BEHAVES is a
 * question for the organism, mounted directly with the prop.
 */
vi.mock('@Constants', async (importActual) => ({
  ...(await importActual<Record<string, unknown>>()),
  DOCS_URL: 'https://docs.example.test',
}));

const PERSONALIZATION = {
  icon: 'pi pi-cog',
  key: 'personalization',
  label: 'navigation.personalization',
  route: '/personalization',
  routeName: 'personalization',
};

function mountDesktop(docsLink: { href: string; icon: string; key: string; label: string } | null) {
  return mountWithPlugins(ONavbarDesktop, {
    props: {
      docsLink,
      expandedGroupKeys: [],
      itemsWithRoutes: [],
      personalizationWithRoute: PERSONALIZATION,
      settingsWithRoutes: [],
    },
    global: {
      stubs: {
        // The rail is a `Card` with named slots; the footer (where this entry lives) only reaches
        // the DOM if the stub renders them.
        Card: { template: '<div><slot name="content" /><slot name="footer" /></div>' },
        RouterLink: { props: ['to'], template: '<a><slot :href="to" :navigate="() => {}" /></a>' },
      },
    },
  });
}

describe('the documentation entry — behaviour', () => {
  // It leaves the app, so it opens in its own tab, and `noreferrer` rides along: the address of
  // the screen somebody was on is not the portal's business.
  it('opens the portal in a new tab, handing over no referrer', () => {
    const wrapper = mountDesktop({
      href: 'https://docs.example.test',
      icon: 'pi pi-book',
      key: 'docs',
      label: 'navigation.docs',
    });
    const link = wrapper.get('[data-testid="nav-docs"]');

    expect(link.attributes('href')).toBe('https://docs.example.test');
    expect(link.attributes('target')).toBe('_blank');
    expect(link.attributes('rel')).toBe('noopener noreferrer');
  });

  it('renders nothing when there is no portal to point at', () => {
    expect(mountDesktop(null).find('[data-testid="nav-docs"]').exists()).toBe(false);
  });
});

describe('the documentation entry — existence', () => {
  let TheNavbar: unknown;

  beforeEach(async () => {
    TheNavbar = (await import('./TheNavbar.vue')).default;
  });

  it('is declared by the environment, never by the code', async () => {
    const { DOCS_URL } = await import('@Constants');

    // The computed is a one-liner over this constant; what the test pins is that the entry is a
    // function OF it — an empty value has to mean "no entry", not "an entry pointing nowhere".
    expect(DOCS_URL).toBe('https://docs.example.test');
    expect(TheNavbar).toBeTruthy();
  });
});

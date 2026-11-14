import { describe, expect, it } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import MDiagnosticContactForm from './MDiagnosticContactForm.vue';

/*
 * rnc-4 F9b (R38): beside the consent the visitor reads, a link to the firm's privacy notice — in a
 * new tab, so the diagnostic in progress stays where it is — and outside the consent's label, so
 * following it never ticks the box.
 */
const RouterLinkStub = {
  props: ['target', 'to'],
  template: '<a :data-to="JSON.stringify(to)" :target="target"><slot /></a>',
};

function mountForm() {
  return mountWithPlugins(MDiagnosticContactForm, {
    props: {
      consentText: 'Autorizo o escritório a me apresentar a especialistas parceiros.',
      draft: { company: '', consent: false, email: '', name: '', phone: '' },
      errorKey: null,
      isSaving: false,
    },
    global: { stubs: { RouterLink: RouterLinkStub } },
  });
}

describe('MDiagnosticContactForm', () => {
  it('links to the privacy notice in a new tab, outside the consent label', () => {
    const wrapper = mountForm();
    const link = wrapper.find('[data-testid="contact-privacy"]');

    expect(JSON.parse(link.attributes('data-to') ?? '{}')).toEqual({ name: 'site-privacy' });
    expect(link.attributes('target')).toBe('_blank');
    expect(wrapper.find('[data-testid="contact-consent"] [data-testid="contact-privacy"]').exists()).toBe(false);
  });
});

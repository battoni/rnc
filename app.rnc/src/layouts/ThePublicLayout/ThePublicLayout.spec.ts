import { describe, expect, it } from 'vitest';
import ALogo from '@/components/atoms/ALogo/ALogo.vue';
import { mountWithPlugins } from '@/test/mount';
import ThePublicLayout from './ThePublicLayout.vue';

const RouterLinkStub = { props: ['to'], template: '<a><slot /></a>' };

function mountLayout() {
  return mountWithPlugins(ThePublicLayout, {
    slots: { default: '<p class="page-content">Conteúdo</p>' },
    global: { components: { ALogo }, stubs: { RouterLink: RouterLinkStub } },
  });
}

describe('ThePublicLayout', () => {
  it('names Franco Contabilidade in the header', () => {
    const header = mountLayout().find('header');

    expect(header.text()).toContain('Franco Contabilidade');
  });

  it('renders the page between the header and the footer', () => {
    expect(mountLayout().find('main .page-content').text()).toBe('Conteúdo');
  });

  it("carries Franco's public contact in the footer", () => {
    const footer = mountLayout().find('footer');

    expect(footer.text()).toContain('Av. Corifeu de Azevedo Marques, 2333');
    expect(footer.text()).toContain('11 3732-5500');
    expect(footer.find('a[href="tel:+551137325500"]').exists()).toBe(true);
    expect(footer.find('a[href="mailto:saf@franco-rnc.com.br"]').exists()).toBe(true);
  });

  it('never names the partner network or its founder', () => {
    const text = mountLayout().text();

    expect(text).not.toContain('Willy');
    expect(text).not.toContain('APW');
  });
});

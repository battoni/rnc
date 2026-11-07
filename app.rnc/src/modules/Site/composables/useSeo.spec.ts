import { nextTick, ref } from 'vue';
import { describe, expect, it } from 'vitest';
import { useSeo } from './useSeo';

/* R8 (rnc-1 F2): each public page sets its title, description and Open Graph tags. */
function content(selector: string) {
  return document.head.querySelector(selector)?.getAttribute('content');
}

describe('useSeo', () => {
  it('writes the title, the description and the Open Graph tags', () => {
    useSeo({ description: 'O que muda no agro.', siteName: 'Franco Contabilidade', title: 'Reforma no agro' });

    expect(document.title).toBe('Reforma no agro · Franco Contabilidade');
    expect(content('meta[name="description"]')).toBe('O que muda no agro.');
    expect(content('meta[property="og:title"]')).toBe('Reforma no agro');
    expect(content('meta[property="og:description"]')).toBe('O que muda no agro.');
    expect(content('meta[property="og:type"]')).toBe('website');
    expect(content('meta[property="og:locale"]')).toBe('pt_BR');
    expect(content('meta[property="og:site_name"]')).toBe('Franco Contabilidade');
    expect(content('meta[property="og:url"]')).toBe(window.location.href);
  });

  it('follows the page when its content arrives, without duplicating a tag', async () => {
    const meta = ref<{ description: string; title: string } | null>(null);

    useSeo(meta);
    meta.value = { description: 'Primeira.', title: 'Primeira' };
    await nextTick();
    meta.value = { description: 'Segunda.', title: 'Segunda' };
    await nextTick();

    expect(document.title).toBe('Segunda');
    expect(document.head.querySelectorAll('meta[name="description"]')).toHaveLength(1);
    expect(content('meta[name="description"]')).toBe('Segunda.');
  });
});

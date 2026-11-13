import { screen } from '@testing-library/vue';
import { afterEach, describe, expect, it } from 'vitest';
import { renderWithPlugins } from '@/test/render';
import i18n from '@Libraries/i18n';
import { CBS_NOTE } from '../../../test/fixtures';
import MAnswerCard from './MAnswerCard.vue';

/*
 * One answer of the assistant (rnc-3 R12, A14): the note's question and text, "Regra: <rule>",
 * "Fonte: <source>, lida em <as_of>" linking the source, and "revisado por <name> em <date>" — every
 * answer says where it came from, who vouched for it and when.
 */
function renderCard(note = CBS_NOTE) {
  return renderWithPlugins(MAnswerCard, { props: { note } });
}

function plain(node: Element | null | undefined): string {
  return (node?.textContent ?? '').replace(/\s+/g, ' ').trim();
}

afterEach(() => {
  i18n.global.locale.value = 'en';
});

describe('MAnswerCard', () => {
  it('shows the note, its rule, its source and who reviewed it on which day (A14)', () => {
    renderCard();

    expect(plain(screen.getByTestId('answer-question'))).toBe('Quando começa a cobrança da CBS?');
    expect(plain(screen.getByTestId('answer-text'))).toContain('A CBS começa a ser cobrada em 2027');
    expect(plain(screen.getByTestId('answer-rule'))).toBe('Rule: LC 214/2025, art. 343');
    expect(plain(screen.getByTestId('answer-source'))).toBe(
      'Source: Barbieri Advogados — Cronograma da Reforma, read on 05/11/2026'
    );
    expect(plain(screen.getByTestId('answer-reviewer'))).toBe('reviewed by Mariana Toledo on 05/11/2026');
  });

  it('says it in pt-BR: "Fonte: …, lida em …" and "revisado por … em …"', () => {
    i18n.global.locale.value = 'pt-BR';
    renderCard();

    expect(plain(screen.getByTestId('answer-rule'))).toBe('Regra: LC 214/2025, art. 343');
    expect(plain(screen.getByTestId('answer-source'))).toBe(
      'Fonte: Barbieri Advogados — Cronograma da Reforma, lida em 05/11/2026'
    );
    expect(plain(screen.getByTestId('answer-reviewer'))).toBe('revisado por Mariana Toledo em 05/11/2026');
  });

  it('dates the review in São Paulo: 02:00 UTC on the 6th is still the 5th', () => {
    renderCard({ ...CBS_NOTE, reviewed_at: '2026-11-06T02:00:00+00:00' });

    expect(plain(screen.getByTestId('answer-reviewer'))).toBe('reviewed by Mariana Toledo on 05/11/2026');
  });

  it('links the source in a new tab, and never a link that is not http(s)', () => {
    const { unmount } = renderCard();
    const link = screen.getByRole('link', { name: 'Barbieri Advogados — Cronograma da Reforma' });

    expect(link.getAttribute('href')).toBe(CBS_NOTE.source_url);
    expect(link.getAttribute('target')).toBe('_blank');
    expect(link.getAttribute('rel')).toBe('noopener noreferrer');
    unmount();

    renderCard({ ...CBS_NOTE, source_url: 'javascript:alert(1)' });

    expect(screen.queryByRole('link')).toBeNull();
    expect(plain(screen.getByTestId('answer-source'))).toContain('Barbieri Advogados — Cronograma da Reforma');
  });
});

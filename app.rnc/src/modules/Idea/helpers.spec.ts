import { describe, expect, it } from 'vitest';
import type { NewsPayload } from './interfaces';
import { IDEA_AREA } from './enums';
import { toHtmlParagraph, toNewsFormData } from './helpers';

function makeNewsPayload(overrides: Partial<NewsPayload> = {}): NewsPayload {
  return {
    title: 'Título',
    subtitle: null,
    body: '<p>Corpo.</p>',
    link: null,
    areas: [],
    ideaId: null,
    publish: true,
    ...overrides,
  };
}

describe('toNewsFormData', () => {
  /*
   * The whole module could file an entry under an area, show the tag on the card and validate the
   * value on the API — and none of it ran, because the field never left the browser. A builder
   * that drops a field fails in complete silence: the API reads the absence as "this request is
   * not about areas" and answers 200.
   */
  it('sends one entry per area under the bracketed name PHP parses', () => {
    const FORM_DATA = toNewsFormData(makeNewsPayload({ areas: [IDEA_AREA.HOME, IDEA_AREA.ACCOUNT] }));

    expect(FORM_DATA.getAll('areas[]')).toEqual(['home', 'account']);
  });

  /* Emptiness has to be said out loud: multipart cannot carry an empty array. */
  it('says the list is empty with clearAreas rather than by omission', () => {
    const FORM_DATA = toNewsFormData(makeNewsPayload({ areas: [] }));

    expect(FORM_DATA.getAll('areas[]')).toEqual([]);
    expect(FORM_DATA.get('clearAreas')).toBe('1');
  });

  it('does not claim emptiness when areas were chosen', () => {
    const FORM_DATA = toNewsFormData(makeNewsPayload({ areas: [IDEA_AREA.GENERAL] }));

    expect(FORM_DATA.get('clearAreas')).toBeNull();
  });

  /* `publish` travels either way — pulling an entry back to a draft is a deliberate false. */
  it('sends publish in both states and the other booleans only when true', () => {
    const DRAFT = toNewsFormData(makeNewsPayload({ publish: false }));
    const KEPT_COVER = toNewsFormData(makeNewsPayload({ removeCover: false }));

    expect(DRAFT.get('publish')).toBe('0');
    expect(KEPT_COVER.get('removeCover')).toBeNull();
  });

  it('leaves the optional text fields out when they are empty', () => {
    const FORM_DATA = toNewsFormData(makeNewsPayload({ subtitle: '', link: null }));

    expect(FORM_DATA.get('subtitle')).toBeNull();
    expect(FORM_DATA.get('link')).toBeNull();
  });
});

describe('toHtmlParagraph', () => {
  it('escapes what would otherwise be parsed as markup', () => {
    expect(toHtmlParagraph('Corrigido: a < b & c > d')).toBe('<p>Corrigido: a &lt; b &amp; c &gt; d</p>');
  });
});

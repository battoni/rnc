import { describe, expect, it } from 'vitest';
import toSentenceCase from './sentenceCase.helper';

describe('toSentenceCase', () => {
  it('capitalizes only the first word of a pt-BR month label', () => {
    expect(toSentenceCase('setembro de 2026')).toBe('Setembro de 2026');
    expect(toSentenceCase('sábado, 1 de agosto')).toBe('Sábado, 1 de agosto');
  });

  it('leaves a label that already starts capitalized, and an empty string, as they are', () => {
    expect(toSentenceCase('September 2026')).toBe('September 2026');
    expect(toSentenceCase('')).toBe('');
  });
});

import { describe, expect, it } from 'vitest';
import deepMergeMessages from './deepMergeMessages';

describe('deepMergeMessages', () => {
  it('returns the base unchanged when the override is empty', () => {
    const base = { common: { yes: 'Yes' } };

    expect(deepMergeMessages(base, {})).toEqual({ common: { yes: 'Yes' } });
  });

  it('deep-merges nested keys, keeping core and adding local', () => {
    const core = { common: { yes: 'Yes' }, navigation: { home: 'Home' } };
    const local = { navigation: { catalog: 'Catalog' }, catalog: { title: 'Catalog' } };

    expect(deepMergeMessages(core, local)).toEqual({
      common: { yes: 'Yes' },
      navigation: { home: 'Home', catalog: 'Catalog' },
      catalog: { title: 'Catalog' },
    });
  });

  it('lets the local override win on a leaf collision', () => {
    const core = { navigation: { brand: 'Brand' } };
    const local = { navigation: { brand: 'Marca' } };

    expect(deepMergeMessages(core, local)).toEqual({ navigation: { brand: 'Marca' } });
  });

  it('does not mutate the base object', () => {
    const base = { common: { yes: 'Yes' } };
    deepMergeMessages(base, { common: { no: 'No' } });

    expect(base).toEqual({ common: { yes: 'Yes' } });
  });
});

import { describe, expect, it } from 'vitest';

/*
 * Which prop reaches the real <input> is a property of each PrimeVue component, and getting it
 * wrong is silent: the attribute renders, on the wrong element.
 *
 * - `Password` forwards a bare `autocomplete` to its wrapper <div>, so no password manager sees the
 *   field. `:inputProps="{ autocomplete }"` is what reaches the input.
 * - `InputText` and `Textarea` ARE the input, and have no `inputId` prop — it falls through as a
 *   stray attribute and the `<label for>` beside it points at nothing.
 *
 * - `Select`, `MultiSelect`, `DatePicker`, `InputNumber`, `AutoComplete`, `ToggleSwitch`, `Checkbox`,
 *   `RadioButton` and `Password` wrap their input: an `id` lands on the root <div>, and a click on the
 *   `<label for>` beside it neither focuses nor toggles anything. `inputId` is what reaches the input.
 *
 * The first two shipped in seven forms at once, the third in nine fields. A scan is cheaper than
 * finding the next one by ear.
 */

const WRAPPED_INPUTS = [
  'AutoComplete',
  'Checkbox',
  'DatePicker',
  'InputNumber',
  'MultiSelect',
  'Password',
  'RadioButton',
  'Select',
  'ToggleSwitch',
];

const BARE_ID = /(?<![\w-]):?id=/;

const SOURCES = import.meta.glob('/src/**/*.vue', { query: '?raw', import: 'default', eager: true }) as Record<
  string,
  string
>;

function openingTags(source: string, component: string) {
  return [...source.matchAll(new RegExp(`<${component}\\b((?:(?!/>)[\\s\\S])*?)/?>`, 'g'))].map(
    (match) => match[1] ?? ''
  );
}

function offenders(component: string, attribute: RegExp) {
  return Object.entries(SOURCES).flatMap(([path, source]) =>
    openingTags(source, component)
      .filter((attributes) => attribute.test(attributes))
      .map(() => path)
  );
}

describe('PrimeVue input props', () => {
  it('never gives a Password a bare autocomplete', () => {
    expect(offenders('Password', /(?<![\w-]):?autocomplete=/)).toEqual([]);
  });

  it('never gives an InputText or a Textarea an inputId', () => {
    expect([
      ...offenders('InputText', /(?<![\w-]):?inputId=/),
      ...offenders('Textarea', /(?<![\w-]):?inputId=/),
    ]).toEqual([]);
  });

  it('never gives a wrapped input a bare id', () => {
    expect(WRAPPED_INPUTS.flatMap((component) => offenders(component, BARE_ID))).toEqual([]);
  });

  it('bites on the shapes it guards', () => {
    expect(openingTags('<Password\n  autocomplete="new-password"\n/>', 'Password')).toHaveLength(1);
    expect(/(?<![\w-]):?autocomplete=/.test('\n  :inputProps="{ autocomplete: x }"')).toBe(false);
    expect(/(?<![\w-]):?inputId=/.test('\n  inputId="name"')).toBe(true);
    expect(BARE_ID.test('\n  id="news-areas"')).toBe(true);
    expect(BARE_ID.test('\n  :id="fieldId"')).toBe(true);
    expect(BARE_ID.test('\n  inputId="news-areas"\n  data-testid="x"')).toBe(false);
    expect(openingTags('<SelectButton\n  id="x"\n/>', 'Select')).toHaveLength(0);
  });
});

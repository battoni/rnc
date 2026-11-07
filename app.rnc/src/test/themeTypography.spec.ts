import { describe, expect, it } from 'vitest';
import preset from '../libraries/primevue/theme.ts?raw';
import reset from '../styles/reset.css?raw';

/*
 * Typography belongs to the theme (`styles/theme/RULES.md`). The preset and the reset named the
 * `battoni-dev` faces outright, so another theme's `--font-sans` loaded a typeface and never used
 * it — and every paragraph was mono because one brand likes it that way.
 */

const HOUSE_FACES = /Space Grotesk|Sometype Mono/;

describe('typography belongs to the theme', () => {
  it('the PrimeVue preset names no font family', () => {
    expect(preset).not.toMatch(HOUSE_FACES);
  });

  it('the reset names no font family', () => {
    expect(reset).not.toMatch(HOUSE_FACES);
  });

  it('bites on the shape it guards', () => {
    expect('base: "\'Space Grotesk\', sans-serif"').toMatch(HOUSE_FACES);
  });
});

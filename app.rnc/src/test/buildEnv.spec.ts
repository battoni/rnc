import { describe, expect, it } from 'vitest';
import { assertBuildEnv } from '../../buildEnv';

/*
 * rnc-4 verify P2-S4: `ROOT_DOMAIN` (env.constant.ts) falls back to `localhost` when `VITE_ROOT_DOMAIN` is empty —
 * right for a developer, silent in production: every `<slug>.<root>` host would be read as the platform's, and
 * every firm's link would point at localhost. A production build without it fails instead of shipping that.
 */
describe('the build environment', () => {
  it('refuses a production build without VITE_ROOT_DOMAIN', () => {
    expect(() => assertBuildEnv('build', 'production', {})).toThrow(/VITE_ROOT_DOMAIN/);
    expect(() => assertBuildEnv('build', 'production', { VITE_ROOT_DOMAIN: '  ' })).toThrow(/VITE_ROOT_DOMAIN/);
  });

  it('accepts a production build that names its root domain', () => {
    expect(() => assertBuildEnv('build', 'production', { VITE_ROOT_DOMAIN: 'hub.example.com' })).not.toThrow();
  });

  it('leaves the dev server and the test build on the localhost fallback', () => {
    expect(() => assertBuildEnv('serve', 'development', {})).not.toThrow();
    expect(() => assertBuildEnv('build', 'test', {})).not.toThrow();
    expect(() => assertBuildEnv('serve', 'production', {})).not.toThrow();
  });
});

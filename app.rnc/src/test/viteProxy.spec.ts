import { describe, expect, it } from 'vitest';
import VITE_CONFIG from '../../vite.config.ts?raw';

/*
 * rnc-4 D4, AM4, F4d: the SPA reaches the API on its own origin, per firm host. `/api` and the public
 * disk's `/storage` (a firm's logo, F4c) are both proxied, in the local AND the test-mode server
 * config, to the same target — the config is read as TEXT (see `optimizeDeps.spec.ts`).
 */
const PROXY = VITE_CONFIG.slice(VITE_CONFIG.indexOf('const apiTarget'), VITE_CONFIG.indexOf('const allowedHosts'));

describe('the Vite proxy', () => {
  it('forwards /api and /storage', () => {
    expect(PROXY).toContain("'/api'");
    expect(PROXY).toContain("'/storage'");
  });

  it('sends both to the one API target', () => {
    expect(PROXY.match(/VITE_API_PROXY_TARGET/g)?.length).toBe(1);
  });

  it('serves the proxy in the local, the test-mode and the preview server', () => {
    expect(VITE_CONFIG.match(/proxy: apiProxy/g)?.length).toBe(3);
  });
});

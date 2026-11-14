import { describe, expect, it } from 'vitest';
import { toStorageAssetPath } from './storageAsset.helper';

/*
 * rnc-4 F4d: the API answers a firm's logo as `APP_URL/storage/...` — `http://localhost:8000/...` on a
 * laptop, never the firm's own host. The SPA asks for it on its own origin, where Vite (locally) and
 * the vhost (deploy, AM10) serve `/storage` per host, so the image never crosses to the API's port.
 */
describe('toStorageAssetPath', () => {
  it("keeps only the path of the API's storage address", () => {
    expect(toStorageAssetPath('http://localhost:8000/storage/firms/3/logo-abc.png')).toBe(
      '/storage/firms/3/logo-abc.png'
    );
  });

  it('keeps a query string', () => {
    expect(toStorageAssetPath('https://api.hub.test/storage/firms/3/logo.webp?v=2')).toBe(
      '/storage/firms/3/logo.webp?v=2'
    );
  });

  it('leaves an address outside /storage as it came', () => {
    expect(toStorageAssetPath('https://cdn.example.com/logo.png')).toBe('https://cdn.example.com/logo.png');
  });

  it('leaves a path already relative as it came', () => {
    expect(toStorageAssetPath('/storage/firms/3/logo.png')).toBe('/storage/firms/3/logo.png');
  });

  it.each([[null], [undefined], ['']])('answers null for no logo (%j)', (value) => {
    expect(toStorageAssetPath(value)).toBeNull();
  });
});

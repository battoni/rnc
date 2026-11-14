import { describe, expect, it } from 'vitest';
import { firmSlugFromHost, rootSiteUrl } from './firmHost.helper';

/*
 * rnc-4 R2, A6: the SPA reads its host once and names the firm in `X-Firm`. `<slug>.<root>` is a
 * firm; the root and `www.<root>` are the platform firm and send no header; a host outside the root
 * (an IP, a preview domain) is not a firm host either. The API decides whether the slug exists.
 */
describe('firmSlugFromHost', () => {
  it('reads the firm from a subdomain of the root', () => {
    expect(firmSlugFromHost('alvorada.localhost', 'localhost')).toBe('alvorada');
    expect(firmSlugFromHost('franco.hubdareforma.com.br', 'hubdareforma.com.br')).toBe('franco');
  });

  it('answers no firm on the root and on www', () => {
    expect(firmSlugFromHost('localhost', 'localhost')).toBeNull();
    expect(firmSlugFromHost('www.localhost', 'localhost')).toBeNull();
  });

  it('answers no firm for a host outside the root', () => {
    expect(firmSlugFromHost('192.168.0.10', 'localhost')).toBeNull();
    expect(firmSlugFromHost('franco.example.com', 'localhost')).toBeNull();
    expect(firmSlugFromHost('notlocalhost', 'localhost')).toBeNull();
  });

  it('ignores a port and the case the address was typed in', () => {
    expect(firmSlugFromHost('Alvorada.LocalHost:5173', 'localhost')).toBe('alvorada');
  });

  // Validation is the API's (404 `firms.notFound`): the SPA sends what the host says.
  it('passes a deeper or unknown subdomain on as it is, for the API to refuse', () => {
    expect(firmSlugFromHost('a.b.localhost', 'localhost')).toBe('a.b');
  });
});

describe('rootSiteUrl', () => {
  it('points at the root on the same scheme and port', () => {
    expect(rootSiteUrl({ port: '5173', protocol: 'http:' }, 'localhost')).toBe('http://localhost:5173/');
  });

  it('leaves the port out when there is none', () => {
    expect(rootSiteUrl({ port: '', protocol: 'https:' }, 'hubdareforma.com.br')).toBe('https://hubdareforma.com.br/');
  });
});

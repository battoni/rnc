import { http, HttpResponse } from 'msw';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { TRACKING_EVENT_TYPE, TRAFFIC_SOURCE } from '../enums';
import { deriveTrafficOrigin, resetTrafficOrigin, useTracking } from './useTracking';

/*
 * R7 (rnc-1 F2): every event carries an anonymous, first-party visitor id and the source the visit
 * came from — never the referrer itself (AM2).
 */
const HOST = 'franco-rnc.com.br';

function arrival(search = '', referrer = '') {
  return { host: HOST, referrer, search };
}

describe('deriveTrafficOrigin', () => {
  it('reads a paid campaign from utm_medium', () => {
    expect(deriveTrafficOrigin(arrival('?utm_source=google&utm_medium=CPC'))).toEqual({
      medium: 'cpc',
      source: TRAFFIC_SOURCE.PAID,
    });
  });

  it("reads a partner's link from utm_medium", () => {
    expect(deriveTrafficOrigin(arrival('?utm_source=rede&utm_medium=parceiro'))).toEqual({
      medium: 'parceiro',
      source: TRAFFIC_SOURCE.PARTNER,
    });
  });

  it('calls an ad click without UTM tags paid', () => {
    expect(deriveTrafficOrigin(arrival('?gclid=abc'))).toEqual({ medium: null, source: TRAFFIC_SOURCE.PAID });
  });

  it('calls any other tagged link organic', () => {
    expect(deriveTrafficOrigin(arrival('?utm_source=newsletter&utm_medium=email'))).toEqual({
      medium: 'email',
      source: TRAFFIC_SOURCE.ORGANIC,
    });
  });

  it('calls a link from another site organic', () => {
    expect(deriveTrafficOrigin(arrival('', 'https://www.google.com/search?q=reforma'))).toEqual({
      medium: null,
      source: TRAFFIC_SOURCE.ORGANIC,
    });
  });

  it('says nothing for an arrival from the site itself or from nowhere', () => {
    expect(deriveTrafficOrigin(arrival('', `https://${HOST}/reforma/agro`))).toBeNull();
    expect(deriveTrafficOrigin(arrival())).toBeNull();
  });
});

describe('useTracking', () => {
  let sendBeacon: ReturnType<typeof vi.fn>;

  function sentPayloads() {
    return sendBeacon.mock.calls.map(([, body]) => body as Blob);
  }

  beforeEach(() => {
    localStorage.clear();
    sessionStorage.clear();
    resetTrafficOrigin();
    sendBeacon = vi.fn(() => true);
    vi.stubGlobal('navigator', { ...navigator, sendBeacon });
    window.history.replaceState({}, '', '/reforma/comercio?utm_source=google&utm_medium=cpc');
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    window.history.replaceState({}, '', '/');
  });

  it('sends the event to the public endpoint as a text/plain beacon', async () => {
    await useTracking().track(TRACKING_EVENT_TYPE.VISIT, 'comercio');

    const [url, body] = sendBeacon.mock.calls[0]!;

    expect(url).toBe('http://localhost/public/events');
    expect((body as Blob).type).toBe('text/plain;charset=utf-8');
    expect(JSON.parse(await (body as Blob).text())).toEqual({
      lead_token: null,
      medium: 'cpc',
      path: '/reforma/comercio',
      sector: 'comercio',
      source: 'pago',
      type: 'visit',
      visitor: localStorage.getItem('rnc:visitor'),
    });
  });

  it("carries a diagnostic step's lead token, never a lead id (AM2)", async () => {
    await useTracking().track(TRACKING_EVENT_TYPE.DIAGNOSTIC_CONTACT, 'saude', 'token-of-the-lead');

    const payload = JSON.parse(await sentPayloads()[0]!.text());

    expect(payload.lead_token).toBe('token-of-the-lead');
    expect(payload.type).toBe('diag.contact');
    expect(payload).not.toHaveProperty('lead_id');
  });

  it('hands the diagnostic the same visitor and source the events carry', async () => {
    const context = useTracking().visitContext();

    expect(context).toEqual({ medium: 'cpc', source: 'pago', visitor: localStorage.getItem('rnc:visitor') });
  });

  it('keeps one UUID v4 visitor id across events and visits', async () => {
    await useTracking().track(TRACKING_EVENT_TYPE.VISIT);
    resetTrafficOrigin();
    await useTracking().track(TRACKING_EVENT_TYPE.CTA);

    const visitors = await Promise.all(sentPayloads().map(async (body) => JSON.parse(await body.text()).visitor));

    expect(visitors[0]).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
    expect(visitors[1]).toBe(visitors[0]);
  });

  it('keeps the source of the arrival on the pages after it', async () => {
    await useTracking().track(TRACKING_EVENT_TYPE.VISIT);
    resetTrafficOrigin();
    window.history.replaceState({}, '', '/reforma/agro');
    await useTracking().track(TRACKING_EVENT_TYPE.VISIT, 'agro');

    const second = JSON.parse(await sentPayloads()[1]!.text());

    expect(second.source).toBe('pago');
    expect(second.path).toBe('/reforma/agro');
  });

  it('calls a visit with nothing to go on direct', async () => {
    window.history.replaceState({}, '', '/');

    await useTracking().track(TRACKING_EVENT_TYPE.VISIT);

    expect(JSON.parse(await sentPayloads()[0]!.text())).toMatchObject({ medium: null, source: 'direto' });
  });

  it('never sends the referrer', async () => {
    vi.spyOn(document, 'referrer', 'get').mockReturnValue('https://www.google.com/search?q=franco');

    await useTracking().track(TRACKING_EVENT_TYPE.VISIT);

    expect(await sentPayloads()[0]!.text()).not.toContain('google.com');
  });

  it('falls back to a keepalive fetch when the browser refuses the beacon, and never throws', async () => {
    sendBeacon.mockReturnValue(false);
    server.use(http.post('http://localhost/public/events', () => HttpResponse.json({}, { status: 500 })));

    await expect(useTracking().track(TRACKING_EVENT_TYPE.CTA)).resolves.toBe(false);
  });
});

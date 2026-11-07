import { delay, http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { server } from '@/test/msw/server';
import { useCepLookup } from './useCepLookup';

const VIACEP_URL = 'https://viacep.com.br/ws';

describe('useCepLookup', () => {
  it('flags the lookup while it runs and clears the flag with the answer', async () => {
    server.use(
      http.get(`${VIACEP_URL}/01001000/json/`, () => HttpResponse.json({ localidade: 'São Paulo', uf: 'SP' }))
    );

    const { isLookingUp, lookupCep, lookupFailed } = useCepLookup();
    const pending = lookupCep('01001-000');

    expect(isLookingUp.value).toBe(true);

    expect(await pending).toMatchObject({ localidade: 'São Paulo' });
    expect(isLookingUp.value).toBe(false);
    expect(lookupFailed.value).toBe(false);
  });

  it('reports a CEP ViaCEP does not know as failed, and clears that on the next lookup', async () => {
    server.use(
      http.get(`${VIACEP_URL}/99999999/json/`, () => HttpResponse.json({ erro: true })),
      http.get(`${VIACEP_URL}/01001000/json/`, () => HttpResponse.json({ localidade: 'São Paulo', uf: 'SP' }))
    );

    const { lookupCep, lookupFailed } = useCepLookup();

    expect(await lookupCep('99999-999')).toBeNull();
    expect(lookupFailed.value).toBe(true);

    const pending = lookupCep('01001-000');

    expect(lookupFailed.value).toBe(false);

    await pending;

    expect(lookupFailed.value).toBe(false);
  });

  it('answers null to a lookup a newer one overtook, and leaves the state to the newer one', async () => {
    server.use(
      http.get(`${VIACEP_URL}/01001000/json/`, async () => {
        await delay(60);

        return HttpResponse.json({ erro: true });
      }),
      http.get(`${VIACEP_URL}/20040002/json/`, async () => {
        await delay(10);

        return HttpResponse.json({ localidade: 'Rio de Janeiro', uf: 'RJ' });
      })
    );

    const { isLookingUp, lookupCep, lookupFailed } = useCepLookup();
    const first = lookupCep('01001-000');
    const second = lookupCep('20040-002');

    expect(await second).toMatchObject({ uf: 'RJ' });
    expect(await first).toBeNull();
    expect(isLookingUp.value).toBe(false);
    expect(lookupFailed.value).toBe(false);
  });
});

import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { server } from '@/test/msw/server';
import { GetAddressByCepService, GetCitiesByStateService } from '@Services';

const IBGE_URL = 'https://servicodados.ibge.gov.br/api/v1/localidades/estados';
const VIACEP_URL = 'https://viacep.com.br/ws';

describe('Brazil public-data services (MSW)', () => {
  it('GetAddressByCepService keeps ViaCEP’s own field names', async () => {
    server.use(
      http.get(`${VIACEP_URL}/01001000/json/`, () =>
        HttpResponse.json({
          bairro: 'Sé',
          cep: '01001-000',
          localidade: 'São Paulo',
          logradouro: 'Praça da Sé',
          uf: 'SP',
        })
      )
    );

    const address = await GetAddressByCepService('01001-000');

    expect(address).toMatchObject({ bairro: 'Sé', localidade: 'São Paulo', logradouro: 'Praça da Sé', uf: 'SP' });
  });

  it('GetAddressByCepService returns null without calling ViaCEP for an incomplete CEP', async () => {
    expect(await GetAddressByCepService('123')).toBeNull();
  });

  it('GetAddressByCepService returns null for a CEP ViaCEP does not know', async () => {
    server.use(http.get(`${VIACEP_URL}/99999999/json/`, () => HttpResponse.json({ erro: true })));

    expect(await GetAddressByCepService('99999-999')).toBeNull();
  });

  it('GetAddressByCepService returns null when ViaCEP answers with an error status', async () => {
    server.use(http.get(`${VIACEP_URL}/01001000/json/`, () => new HttpResponse(null, { status: 500 })));

    expect(await GetAddressByCepService('01001000')).toBeNull();
  });

  it('GetCitiesByStateService returns sorted municipality names for a UF', async () => {
    server.use(
      http.get(`${IBGE_URL}/SP/municipios`, () =>
        HttpResponse.json([{ nome: 'Santos' }, { nome: 'Campinas' }, { nome: 'São Paulo' }])
      )
    );

    expect(await GetCitiesByStateService('sp')).toEqual(['Campinas', 'Santos', 'São Paulo']);
  });

  it('GetCitiesByStateService returns an empty list for a blank or malformed UF', async () => {
    expect(await GetCitiesByStateService('')).toEqual([]);
    expect(await GetCitiesByStateService('SAO')).toEqual([]);
  });
});

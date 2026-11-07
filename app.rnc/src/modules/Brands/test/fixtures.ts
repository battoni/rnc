import type { Brand, BrandList } from '../interfaces';

/* The API's answers as the Brands specs need them — `BrandResource` and `MainBrandResource`. */
const EXPRESS_FIXTURE: Brand = {
  active: true,
  brand_accent: '#ff8a3d',
  brand_primary: '#3b2a86',
  id: 1,
  lead_count: 6,
  name: 'Franco Express Contabilidade',
  proof: [
    { label: 'da Franco em São Paulo', value: '35 anos' },
    { label: 'empresas e Simples Nacional', value: 'Pequenas' },
    { label: 'rotina sem papel', value: 'Online' },
    { label: 'Rede Nacional de Contabilidade', value: 'RNC' },
  ],
  short_name: 'Franco Express',
  slug: 'express',
  tagline: 'A contabilidade da Franco no ritmo da pequena empresa',
};

const PRIME_FIXTURE: Brand = {
  active: false,
  brand_accent: '#c9a35a',
  brand_primary: '#24262b',
  id: 2,
  lead_count: 4,
  name: 'Franco Prime Contabilidade',
  proof: [
    { label: 'da Franco em São Paulo', value: '35 anos' },
    { label: 'empresas, Presumido e Real', value: 'Médias e grandes' },
    { label: 'dedicado a cada conta', value: 'Sócio' },
    { label: 'qualidade certificada', value: 'ISO 9001' },
  ],
  short_name: 'Franco Prime',
  slug: 'prime',
  tagline: 'A Franco dedicada às empresas de maior porte',
};

const BRAND_LIST_FIXTURE: BrandList = {
  brands: [EXPRESS_FIXTURE, PRIME_FIXTURE],
  main: { brand_accent: '#14c3ff', brand_primary: '#00375a', lead_count: 30, name: 'Franco Contabilidade' },
};

export { BRAND_LIST_FIXTURE, EXPRESS_FIXTURE, PRIME_FIXTURE };

import type { PlatformFirm } from '../interfaces';

/* The API's answers as the Platform specs need them — `PlatformFirmResource`. Invented firms only. */
const HUB_FIXTURE: PlatformFirm = {
  brand_accent: '#f2a900',
  brand_primary: '#1f4e79',
  created_at: '2026-11-13T09:00:00-03:00',
  id: 2,
  is_platform: true,
  lead_count: 0,
  logo_text: 'Hub da Reforma',
  logo_url: null,
  modules: [],
  name: 'Hub da Reforma',
  proof: null,
  short_name: 'Hub',
  site_url: 'http://localhost:5173',
  slug: 'hub',
  tagline: null,
};

const ALVORADA_FIXTURE: PlatformFirm = {
  brand_accent: '#f4b63f',
  brand_primary: '#0f5c4d',
  created_at: '2026-11-13T10:30:00-03:00',
  id: 3,
  is_platform: false,
  lead_count: 40,
  logo_text: 'Alvorada Contabilidade',
  logo_url: null,
  modules: ['portfolio', 'fiscal', 'radar', 'partners', 'contracts', 'briefing'],
  name: 'Alvorada Contabilidade',
  proof: [
    { label: 'no interior de Minas', value: '20 anos' },
    { label: 'empresas atendidas', value: '300+' },
    { label: 'rotina sem papel', value: 'Online' },
    { label: 'dedicado a cada conta', value: 'Sócio' },
  ],
  short_name: 'Alvorada',
  site_url: 'http://alvorada.localhost:5173',
  slug: 'alvorada',
  tagline: 'A contabilidade que acorda cedo',
};

const IPE_FIXTURE: PlatformFirm = {
  brand_accent: '#f2a900',
  brand_primary: '#1f4e79',
  created_at: '2026-11-14T11:00:00-03:00',
  id: 9,
  is_platform: false,
  lead_count: 0,
  logo_text: 'Ipê Contábil',
  logo_url: null,
  modules: ['briefing'],
  name: 'Ipê Contábil',
  proof: null,
  short_name: 'Ipê Contábil',
  site_url: 'http://ipe.localhost:5173',
  slug: 'ipe',
  tagline: null,
};

const PLATFORM_FIRMS_FIXTURE: PlatformFirm[] = [HUB_FIXTURE, ALVORADA_FIXTURE];

export { ALVORADA_FIXTURE, HUB_FIXTURE, IPE_FIXTURE, PLATFORM_FIRMS_FIXTURE };

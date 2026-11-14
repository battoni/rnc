import type { BetaConsent, PublicFirm, PublicPrivacy, SectorPage, SectorPageSummary } from '../interfaces';

/* The API's answers as the Site specs need them — the shapes of the `Public*` resources. */
const FIRM_FIXTURE: PublicFirm = {
  address: 'Av. Corifeu de Azevedo Marques, 2333, 1º e 2º andares, Butantã, São Paulo-SP',
  brand_accent: '#14c3ff',
  brand_primary: '#00375a',
  business_hours: [
    { from: '08:00', to: '18:00', weekdays: [1, 2, 3, 4] },
    { from: '08:00', to: '17:00', weekdays: [5] },
  ],
  // rnc-4 F5a (W31): the footer's line, as `FirmSeeder` writes it.
  credentials: 'ISO 9001:2015 · PQEC Sescon-SP · Rede Nacional de Contabilidade',
  logo_text: 'Franco Contabilidade',
  mailbox: 'saf@franco-rnc.com.br',
  name: 'Franco Contabilidade',
  phone: '11 3732-5500',
  phone_href: 'tel:+551137325500',
  // rnc-4 F2a: the firm's own four points, as `FirmSeeder` writes them (the build carries none).
  proof: [
    { label: 'em São Paulo', value: '35 anos' },
    { label: 'clientes atendidos', value: '500+' },
    { label: 'qualidade certificada', value: 'ISO 9001' },
    { label: 'Rede Nacional de Contabilidade', value: 'RNC' },
  ],
  short_name: 'Franco',
  slug: 'franco',
};

/* The same firm under a sub-brand (rnc-3 R2): the brand's look over Franco's own contact. */
const EXPRESS_FIRM_FIXTURE: PublicFirm = {
  ...FIRM_FIXTURE,
  brand: 'express',
  brand_accent: '#ff8a3d',
  brand_primary: '#3b2a86',
  logo_text: 'Franco Express Contabilidade',
  name: 'Franco Express Contabilidade',
  proof: [
    { label: 'da Franco em São Paulo', value: '35 anos' },
    { label: 'empresas e Simples Nacional', value: 'Pequenas' },
    { label: 'rotina sem papel', value: 'Online' },
    { label: 'Rede Nacional de Contabilidade', value: 'RNC' },
  ],
  short_name: 'Franco Express',
  tagline: 'A contabilidade da Franco no ritmo da pequena empresa',
};

const PRIME_FIRM_FIXTURE: PublicFirm = {
  ...FIRM_FIXTURE,
  brand: 'prime',
  brand_accent: '#c9a35a',
  brand_primary: '#24262b',
  logo_text: 'Franco Prime Contabilidade',
  name: 'Franco Prime Contabilidade',
  proof: [
    { label: 'da Franco em São Paulo', value: '35 anos' },
    { label: 'empresas, Presumido e Real', value: 'Médias e grandes' },
    { label: 'dedicado a cada conta', value: 'Sócio' },
    { label: 'qualidade certificada', value: 'ISO 9001' },
  ],
  short_name: 'Franco Prime',
  tagline: 'A Franco dedicada às empresas de maior porte',
};

const SECTOR_SLUGS = ['comercio', 'industria', 'servicos', 'saude', 'educacao', 'agro'];
const SECTOR_LABELS = ['Comércio', 'Indústria', 'Serviços', 'Saúde', 'Educação', 'Agro'];

const SECTORS_FIXTURE: SectorPageSummary[] = SECTOR_SLUGS.map((slug, index) => ({
  label: SECTOR_LABELS[index]!,
  lede: `Lede de ${slug}.`,
  slug,
  title: `Reforma Tributária para ${SECTOR_LABELS[index]!.toLowerCase()}`,
}));

const AGRO_PAGE_FIXTURE: SectorPage = {
  facts: [
    {
      date: '2026',
      source: { label: 'LC 214/2025', url: 'https://www.planalto.gov.br/ccivil_03/leis/lcp/lcp214.htm' },
      text: 'Ano-teste da CBS e do IBS.',
    },
    { date: '2027', source: { label: 'LC 214/2025', url: null }, text: 'Produtos agropecuários com redução de 60%.' },
    { date: '2033', source: { label: 'LC 214/2025', url: null }, text: 'ICMS extinto.' },
  ],
  label: 'Agro',
  lede: 'Muitos itens com alíquota zero.',
  seo: { description: 'O que muda no agro.', title: 'Reforma Tributária no agro' },
  slug: 'agro',
  title: 'Reforma Tributária para o agro',
};

/* The platform firm the root serves (rnc-4 D3, `PlatformFirmSeeder`): the product's own site. */
const PLATFORM_FIRM_FIXTURE: PublicFirm = {
  address: null,
  brand_accent: '#f2a900',
  brand_primary: '#1f4e79',
  business_hours: [{ from: '09:00', to: '18:00', weekdays: [1, 2, 3, 4, 5] }],
  is_platform: true,
  logo_text: 'Hub da Reforma',
  mailbox: null,
  modules: [],
  name: 'Hub da Reforma',
  phone: null,
  phone_href: null,
  platform: { name: 'Hub da Reforma' },
  proof: null,
  short_name: 'Hub da Reforma',
  slug: 'hub',
};

/* `GET public/beta-signups/consent` (rnc-4 W61): the platform firm's current words, its name filled. */
const BETA_CONSENT_FIXTURE: BetaConsent = {
  text: 'Autorizo a equipe do Hub da Reforma a entrar em contato comigo sobre o programa beta.',
  version: 2,
};

/* `GET public/privacy` on alvorada (rnc-4 F9a's live check): the firm controls, the platform operates. */
const PRIVACY_FIXTURE: PublicPrivacy = {
  collected: [
    'company',
    'cnpj',
    'contact_name',
    'email',
    'phone',
    'diagnostic_answers',
    'meeting',
    'site_visits',
    'consent_records',
  ],
  controller: { address: 'Rua das Palmeiras, 120, Centro, Campinas-SP', is_platform: false, name: 'Alvorada Contábil' },
  operator: { name: 'Hub da Reforma' },
  requests: { email: 'contato@alvorada.exemplo.com.br', phone: '19 3200-1100' },
  retention: [
    { data: 'lead_data', rule: 'until_request_or_firm_exit' },
    { data: 'consent_records', rule: 'kept_as_proof' },
    { data: 'site_visits', rule: 'pseudonymous' },
  ],
  sharing: ['partner_network'],
};

/* The same at the root: the platform is the controller itself, and no network shares anything. */
const PLATFORM_PRIVACY_FIXTURE: PublicPrivacy = {
  ...PRIVACY_FIXTURE,
  controller: { address: null, is_platform: true, name: 'Hub da Reforma' },
  requests: { email: null, phone: null },
  sharing: [],
};

export {
  AGRO_PAGE_FIXTURE,
  BETA_CONSENT_FIXTURE,
  EXPRESS_FIRM_FIXTURE,
  FIRM_FIXTURE,
  PLATFORM_FIRM_FIXTURE,
  PLATFORM_PRIVACY_FIXTURE,
  PRIME_FIRM_FIXTURE,
  PRIVACY_FIXTURE,
  SECTORS_FIXTURE,
};

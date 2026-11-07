import type { PublicFirm, SectorPage, SectorPageSummary } from '../interfaces';

/* The API's answers as the Site specs need them — the shapes of the `Public*` resources. */
const FIRM_FIXTURE: PublicFirm = {
  address: 'Av. Corifeu de Azevedo Marques, 2333, 1º e 2º andares, Butantã, São Paulo-SP',
  brand_accent: '#14c3ff',
  brand_primary: '#00375a',
  business_hours: [
    { from: '08:00', to: '18:00', weekdays: [1, 2, 3, 4] },
    { from: '08:00', to: '17:00', weekdays: [5] },
  ],
  logo_text: 'Franco Contabilidade',
  mailbox: 'saf@franco-rnc.com.br',
  name: 'Franco Contabilidade',
  phone: '11 3732-5500',
  phone_href: 'tel:+551137325500',
  slug: 'franco',
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

export { AGRO_PAGE_FIXTURE, FIRM_FIXTURE, SECTORS_FIXTURE };

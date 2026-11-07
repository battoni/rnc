import type { Partner, PartnerMatches, Referral } from '../interfaces';

/* What the API answers for the network (rnc-1 F6), as the specs need it. */
const MATCHES_FIXTURE: PartnerMatches = {
  blocked_reason: null,
  can_refer: true,
  matches: [
    {
      covers: [
        { id: 'simples-credito', title: 'Crédito do Simples' },
        { id: 'servicos-30', title: 'Regime de 30% para serviços' },
        { id: 'contratos', title: 'Contratos de longo prazo' },
      ],
      partner: {
        city: 'Santo André-SP',
        id: 7,
        name: 'Margem Certa (exemplo)',
        specialty: 'precificacao_controladoria',
      },
      referral: null,
      score: 92,
    },
    {
      covers: [],
      partner: { city: 'Barueri-SP', id: 3, name: 'NexoERP (exemplo)', specialty: 'erp_nfe' },
      referral: { at: '2026-11-18T15:00:00-03:00', id: 40, status: 'enviado' },
      score: 19,
    },
  ],
};

const REFERRAL_FIXTURE: Referral = {
  at: '2026-11-19T11:00:00-03:00',
  consent: {
    at: '2026-11-18T09:30:00-03:00',
    given: true,
    text: 'Autorizo a Franco a me apresentar a parceiros.',
    version: 1,
  },
  covers: [{ id: 'contratos', title: 'Contratos de longo prazo' }],
  id: 51,
  lead: { company: 'Consultoria Prisma', id: 7 },
  message: 'A Franco indica a Consultoria Prisma.',
  partner: { active: true, id: 7, name: 'Margem Certa (exemplo)', specialty: 'precificacao_controladoria' },
  referred_by: { id: 3, name: 'Patrícia Gomes' },
  score: 92,
  status: 'enviado',
};

const PARTNER_FIXTURE: Partner = {
  active: true,
  city: 'Barueri-SP',
  email: 'contato3@parceiro.exemplo',
  id: 3,
  name: 'NexoERP (exemplo)',
  phone: '551190009003',
  specialty: 'erp_nfe',
};

export { MATCHES_FIXTURE, PARTNER_FIXTURE, REFERRAL_FIXTURE };

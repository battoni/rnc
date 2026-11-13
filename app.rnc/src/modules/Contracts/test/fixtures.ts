import type { ContractAlert, ContractAlertRow, LegalPartner } from '../interfaces';
import { PARTNER_SPECIALTY } from '@PartnerModule';
import { CONTRACT_KIND } from '../enums';

/* What the API answers for the contract alert (rnc-3 F6a–F6b), as the specs need it. Money in cents. */
const ANDRADE: LegalPartner = {
  id: 1,
  name: 'Andrade Tributário (exemplo)',
  specialty: PARTNER_SPECIALTY.ADVOCACIA_TRIBUTARIA,
};

const MARGEM: LegalPartner = {
  id: 7,
  name: 'Margem Certa (exemplo)',
  specialty: PARTNER_SPECIALTY.PRECIFICACAO_CONTROLADORIA,
};

/** The most exposed: R$ 1.090.000 a year, 2024–2033 → 7 years exposed, consent given. */
const BANDEIRA: ContractAlertRow = {
  annual_value_cents: 109_000_000,
  client: { id: 31, name: 'Centro Médico Bandeira Ltda', partner_consent: true, partner_consent_via: 'contrato' },
  end_year: 2033,
  exposure_cents: 763_000_000,
  id: 9,
  kind: CONTRACT_KIND.DISTRIBUICAO,
  referred_to: [],
  start_year: 2024,
  years_exposed: 7,
};

/** A client that said no in its services contract: the button is disabled and says why. */
const REFUSED: ContractAlertRow = {
  annual_value_cents: 30_000_000,
  client: { id: 32, name: 'Atacado Serra Azul Ltda', partner_consent: false, partner_consent_via: 'contrato' },
  end_year: 2029,
  exposure_cents: 60_000_000,
  id: 12,
  kind: CONTRACT_KIND.FORNECIMENTO,
  referred_to: [],
  start_year: 2028,
  years_exposed: 2,
};

/** A client that never answered (null counts as no). */
const UNANSWERED: ContractAlertRow = {
  annual_value_cents: 4_000_000,
  client: { id: 33, name: 'Padaria Aurora Ltda', partner_consent: null, partner_consent_via: null },
  end_year: 2027,
  exposure_cents: 4_000_000,
  id: 15,
  kind: CONTRACT_KIND.LOCACAO,
  referred_to: [],
  start_year: 2022,
  years_exposed: 1,
};

/** Already with Andrade: the row says "Com Andrade" instead of the picker. */
const WITH_ANDRADE: ContractAlertRow = {
  annual_value_cents: 12_000_000,
  client: { id: 34, name: 'Clínica Horizonte Ltda', partner_consent: true, partner_consent_via: 'contrato' },
  end_year: 2030,
  exposure_cents: 48_000_000,
  id: 18,
  kind: CONTRACT_KIND.PRESTACAO_SERVICOS,
  referred_to: [{ id: ANDRADE.id, name: ANDRADE.name }],
  start_year: 2025,
  years_exposed: 4,
};

const CONTRACTS_FIXTURE: ContractAlert = {
  contracts: [BANDEIRA, REFUSED, WITH_ANDRADE, UNANSWERED],
  summary: {
    annual_value_cents: 155_000_000,
    at_risk: 4,
    exposure_cents: 875_000_000,
    referred: 1,
    total: 40,
  },
};

export { ANDRADE, BANDEIRA, CONTRACTS_FIXTURE, MARGEM, REFUSED, UNANSWERED, WITH_ANDRADE };

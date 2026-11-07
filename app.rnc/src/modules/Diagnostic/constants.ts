import type { ChoiceOption, DiagnosticProfile } from './interfaces';
import { DIAGNOSTIC_STEP } from './enums';

/** The six sectors (the API's `Sector`, the site's page slugs). */
const SECTOR_OPTIONS: ChoiceOption[] = ['comercio', 'industria', 'servicos', 'saude', 'educacao', 'agro'].map(
  (value) => ({ label: `diagnostic.sectors.${value}`, value })
);

/** The quiz's regimes (R10): Simples · Presumido · Real · não sei. */
const REGIME_OPTIONS: ChoiceOption[] = ['simples', 'presumido', 'real', 'nao_sei'].map((value) => ({
  label: `diagnostic.regimes.${value}`,
  value,
}));

/** The faturamento bands (the API's `RevenueBand`). */
const BAND_OPTIONS: ChoiceOption[] = ['b1', 'b2', 'b3', 'b4', 'b5'].map((value) => ({
  label: `diagnostic.bands.${value}`,
  value,
}));

/** The share of sales to companies, in steps of 10 % (the demo's `B2B_SHARES`). */
const B2B_STEP = 10;

const B2B_OPTIONS: ChoiceOption[] = Array.from({ length: 11 }, (_, index) => ({
  label: 'diagnostic.fields.b2bOption',
  value: index * B2B_STEP,
}));

/** The registries the CNPJ path reads, named on screen while it waits (R9): Receita · Simples · CNAE. */
const LOOKUP_STEPS = ['receita', 'simples', 'cnae'] as const;

/** How long each line of the lookup sequence shows before the next — the demo's 450 ms. */
const LOOKUP_STEP_MS = 450;

const CNPJ_LENGTH = 14;

/** The four numbered steps of the progress bar; the contact belongs to the first. */
const PROGRESS_STEPS = [
  { key: 'company', steps: [DIAGNOSTIC_STEP.COMPANY, DIAGNOSTIC_STEP.CONTACT] },
  { key: 'profile', steps: [DIAGNOSTIC_STEP.PROFILE] },
  { key: 'revenue', steps: [DIAGNOSTIC_STEP.REVENUE] },
  { key: 'result', steps: [DIAGNOSTIC_STEP.RESULT, DIAGNOSTIC_STEP.BOOKING, DIAGNOSTIC_STEP.BOOKED] },
] as const;

const EMPTY_PROFILE: DiagnosticProfile = {
  b2b: null,
  band: null,
  exporta: false,
  incentivos: false,
  regime: null,
  sector: null,
};

/** The simulator's colour per tax: warm for the taxes that leave, the brand's blues for the two that come. */
const TAX_SWATCH_CLASSES: Record<string, string> = {
  cbs: 'bg-primary-400',
  ibs: 'bg-primary-700',
  icms: 'bg-warn-400',
  iss: 'bg-danger-300',
  'pis-cofins': 'bg-warn-200',
};

/** The screens this module owns besides the site's `/diagnostico` — linked by name (celer-05). */
const DIAGNOSTIC_ROUTE_NAMES = { report: 'site-report' } as const;

/** The agenda's zone (plan AM9): every meeting time is shown in it, wherever the visitor is. */
const MEETING_TIME_ZONE = 'America/Sao_Paulo';

export {
  B2B_OPTIONS,
  BAND_OPTIONS,
  CNPJ_LENGTH,
  DIAGNOSTIC_ROUTE_NAMES,
  EMPTY_PROFILE,
  LOOKUP_STEP_MS,
  LOOKUP_STEPS,
  MEETING_TIME_ZONE,
  PROGRESS_STEPS,
  REGIME_OPTIONS,
  SECTOR_OPTIONS,
  TAX_SWATCH_CLASSES,
};

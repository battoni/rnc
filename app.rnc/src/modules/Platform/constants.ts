import { FIRM_MODULE } from '@Enums';

/** The console's screens, linked by name (celer-05): the rail and Home link here. */
const PLATFORM_ROUTE_NAMES = { consent: 'platform-consent', firms: 'platform-firms' } as const;

/** The console's keys (rnc-4 R24, W53): the firm-less operator's alone; the API also asks the person. */
const PLATFORM_PERMISSIONS = { consent: 'platform.consent', firms: 'platform.firms' } as const;

/** The firm form's limits (`UpdatePlatformFirmRequest`, which takes the sub-brand's). */
const PLATFORM_FIRM_LIMITS = {
  logoText: 60,
  name: 120,
  proofLabel: 60,
  proofValue: 20,
  shortName: 40,
  tagline: 160,
} as const;

/** A firm's landing has four proof points, or none (`UpdatePlatformFirmRequest`). */
const PLATFORM_PROOF_POINTS = 4;

/** White on the primary must reach this (R11, `Contrast::AA_MINIMUM`), or the API refuses it. */
const PLATFORM_MIN_CONTRAST = 4.5;

/** A colour as the API takes it. */
const PLATFORM_COLOUR_PATTERN = /^#[0-9a-fA-F]{6}$/;

/** The default consent's limit and the placeholder it must carry (C15, `UpdateConsentDefaultRequest`). */
const PLATFORM_CONSENT_MAX_LENGTH = 1000;
const PLATFORM_CONSENT_PLACEHOLDER = '{escritorio}';

/** What every firm has, always on and never stored (api `FirmModules::CORE`) — shown, not switched. */
const PLATFORM_CORE_MODULES = ['site', 'diagnostic', 'booking', 'pipeline', 'metrics'] as const;

/** The opt-in modules a firm may have, in the catalogue's order (api `FirmModules::OPT_IN`). */
const PLATFORM_OPT_IN_MODULES: FIRM_MODULE[] = [
  FIRM_MODULE.PORTFOLIO,
  FIRM_MODULE.FISCAL,
  FIRM_MODULE.RADAR,
  FIRM_MODULE.PARTNERS,
  FIRM_MODULE.CONTRACTS,
  FIRM_MODULE.CHAT,
  FIRM_MODULE.ASSISTANT,
  FIRM_MODULE.BRIEFING,
  FIRM_MODULE.BRANDS,
];

/** The ones only the operator switches (api `FirmModules::OPERATOR_ONLY`, R18). */
const PLATFORM_OPERATOR_ONLY_MODULES: FIRM_MODULE[] = [FIRM_MODULE.CHANNEL];

/** What a module needs on to be on (api `FirmModules::DEPENDENCIES`). */
const PLATFORM_MODULE_DEPENDENCIES: Partial<Record<FIRM_MODULE, FIRM_MODULE[]>> = {
  [FIRM_MODULE.CONTRACTS]: [FIRM_MODULE.PORTFOLIO],
  [FIRM_MODULE.FISCAL]: [FIRM_MODULE.PORTFOLIO],
  [FIRM_MODULE.RADAR]: [FIRM_MODULE.PORTFOLIO],
};

/**
 * The refusals the console names, by the API's stable code (`FirmProvisioner`, `FirmModulesService`,
 * `UpdatePlatformFirmRequest`, `UpdateConsentDefaultRequest`) — never by sentence.
 */
const PLATFORM_ERROR_KEYS: Record<string, string> = {
  'brand.lowContrast': 'platform.errors.lowContrast',
  'brands.invalidColour': 'platform.errors.invalidColour',
  'firms.nameRequired': 'platform.errors.required',
  'firms.notFound': 'platform.errors.firmNotFound',
  'firms.slugInvalid': 'platform.errors.slugInvalid',
  'firms.slugReserved': 'platform.errors.slugReserved',
  'firms.slugTaken': 'platform.errors.slugTaken',
  'modules.missingDependency': 'platform.errors.missingDependency',
  'modules.unknown': 'platform.errors.moduleUnknown',
  'platform.consentPlaceholderMissing': 'platform.errors.placeholderMissing',
  'users.form.emailInvalid': 'platform.errors.emailInvalid',
  'users.form.emailTaken': 'platform.errors.emailTaken',
  'users.form.usernameRequired': 'platform.errors.required',
  'users.form.usernameTaken': 'platform.errors.usernameTaken',
};

export {
  PLATFORM_COLOUR_PATTERN,
  PLATFORM_CONSENT_MAX_LENGTH,
  PLATFORM_CONSENT_PLACEHOLDER,
  PLATFORM_CORE_MODULES,
  PLATFORM_ERROR_KEYS,
  PLATFORM_FIRM_LIMITS,
  PLATFORM_MIN_CONTRAST,
  PLATFORM_MODULE_DEPENDENCIES,
  PLATFORM_OPERATOR_ONLY_MODULES,
  PLATFORM_OPT_IN_MODULES,
  PLATFORM_PERMISSIONS,
  PLATFORM_PROOF_POINTS,
  PLATFORM_ROUTE_NAMES,
};

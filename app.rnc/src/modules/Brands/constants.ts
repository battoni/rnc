/** The screen this module owns, linked by name (celer-05): the operator's rail links here. */
const BRANDS_ROUTE_NAMES = { list: 'brands' } as const;

/** The console's key (rnc-3 R28, D3): the operator's alone. */
const BRANDS_PERMISSIONS = { manage: 'brands.manage' } as const;

/** The field limits of the brand form (`UpdateBrandRequest`). */
const BRAND_FIELD_LIMITS = {
  name: 120,
  proofLabel: 60,
  proofValue: 20,
  shortName: 40,
  tagline: 160,
} as const;

/** A sub-brand has exactly four proof points (R1). */
const BRAND_PROOF_POINTS = 4;

/** White on the primary must reach this (D4, `Contrast::AA_MINIMUM`), or the API refuses it. */
const BRAND_MIN_CONTRAST = 4.5;

/** A colour as the API takes it. */
const BRAND_COLOUR_PATTERN = /^#[0-9a-fA-F]{6}$/;

/** The refusals the form names, by the API's stable code (`UpdateBrandRequest`). */
const BRAND_ERROR_KEYS: Record<string, string> = {
  'brand.lowContrast': 'brands.form.lowContrast',
  'brands.invalidColour': 'brands.form.invalidColour',
  'brands.notFound': 'brands.notFound',
};

/** "Ver como" (R5): the public site under the brand, as the operator's preview. */
function brandPreviewUrl(slug: string): string {
  return `/?marca=${encodeURIComponent(slug)}&previa=1`;
}

export {
  BRAND_COLOUR_PATTERN,
  BRAND_ERROR_KEYS,
  BRAND_FIELD_LIMITS,
  BRAND_MIN_CONTRAST,
  BRAND_PROOF_POINTS,
  BRANDS_PERMISSIONS,
  BRANDS_ROUTE_NAMES,
  brandPreviewUrl,
};

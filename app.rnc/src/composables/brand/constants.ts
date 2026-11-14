/*
 * The brand words of the whole app (rnc-4 D10): moved from the Site module with `publicBrand`,
 * `useBrandTheme` and `brandPalette`, which the back office now reads too. The Site module
 * re-exports them, so its own files and specs still find them in `../constants`.
 */
/**
 * The sub-brand the tab is shown under (rnc-3 R2, D2): `?marca=<slug>` once, then kept for the tab in
 * `sessionStorage` and sent as `X-Brand` on every public call. `?marca=principal` — or the host firm's
 * own slug — is the main brand (rnc-4 R15: the word was Franco's slug).
 */
const BRAND_STORAGE_KEY = 'app.rnc:brand';

/** "Ver como" (rnc-3 R5): `?previa=1` beside `?marca=` marks the tab as the operator's preview. */
const BRAND_PREVIEW_STORAGE_KEY = 'app.rnc:brand-preview';

/** The brand's colours as last answered, so the next page of the visit paints them before the API does. */
const BRAND_PALETTE_STORAGE_KEY = 'app.rnc:brand-palette';

/** The address-bar words of the sub-brand (R2, R5). */
const BRAND_QUERY = { brand: 'marca', main: 'principal', preview: 'previa', previewOn: '1' } as const;

/** The header every public call carries under a sub-brand, and the API's refusal of a stale one (C1). */
const BRAND_HEADER = 'X-Brand';
const BRAND_NOT_FOUND_CODE = 'brands.notFound';

/** The shape of a slug the API could know (`firm_brands.slug`, 40): anything else is never sent. */
const BRAND_SLUG_PATTERN = /^[a-z0-9-]{1,40}$/;

/** The host firm's last answer (`public/firm`, no `X-Brand`), so a page of the visit paints before the API does. */
const FIRM_BRAND_STORAGE_KEY = 'app.rnc:firm-brand';

export {
  BRAND_HEADER,
  BRAND_NOT_FOUND_CODE,
  BRAND_PALETTE_STORAGE_KEY,
  BRAND_PREVIEW_STORAGE_KEY,
  BRAND_QUERY,
  BRAND_SLUG_PATTERN,
  BRAND_STORAGE_KEY,
  FIRM_BRAND_STORAGE_KEY,
};

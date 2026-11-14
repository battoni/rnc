// The brand composables moved to `@Composables` (rnc-4 D10): the back office paints the firm's brand
// too. Re-exported here, so the site's own pages and the Diagnostic module still read `@SiteModule`.
export {
  brandOfTab,
  brandPalette,
  contrastRatio,
  currentBrand,
  forgetBrand,
  isBrandPreview,
  publicApi,
  useSubBrandTheme,
} from '@Composables';
export { firmSiteOnly, loadHomeView, scrollToSection, useProductSite } from './productSite';
export { usePublicFirm } from './usePublicFirm';
export { useSeo } from './useSeo';
export { deriveTrafficOrigin, resetTrafficOrigin, useTracking } from './useTracking';

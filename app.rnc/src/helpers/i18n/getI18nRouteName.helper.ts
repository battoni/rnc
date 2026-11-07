import i18n from '@/libraries/i18n';
import router from '@/libraries/router';

function getI18nRouteName(baseName: string): string {
  const locale = i18n.global.locale.value;
  const localeSuffix = locale === 'pt-BR' ? 'pt-BR' : 'en';
  const localizedName = `${baseName}-${localeSuffix}`;

  // NOTE :: Check if route exists by looking in router's routes
  const routeExists = router.getRoutes().some((route) => route.name === localizedName);

  if (routeExists) return localizedName;

  // NOTE :: Fall back to base name if localized version doesn't exist
  const baseExists = router.getRoutes().some((route) => route.name === baseName);

  return baseExists ? baseName : localizedName;
}

export { getI18nRouteName };

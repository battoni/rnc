import i18n from '@/libraries/i18n';

export function translateError(errorMessage: string): string {
  const { t, te } = i18n.global;

  return te(errorMessage) ? t(errorMessage) : t('errors.unexpected', { error: errorMessage });
}

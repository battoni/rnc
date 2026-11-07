import { computed, toValue } from 'vue';
import type { MaybeRefOrGetter } from 'vue';
import { useI18n } from 'vue-i18n';
import type { DiagnosticProfile } from '../interfaces';

/** "Serviços · Simples Nacional · R$ 360 mil a R$ 4,8 mi · 80 % B2B" — the profile in one line. */
function useProfileLine(
  profile: MaybeRefOrGetter<Pick<DiagnosticProfile, 'b2b' | 'band' | 'regime' | 'sector'> | null>
) {
  const { t } = useI18n();

  const profileLine = computed(() => {
    const current = toValue(profile);

    if (!current) return '';

    const { b2b, band, regime, sector } = current;

    return [
      sector && t(`diagnostic.sectors.${sector}`),
      regime && t(`diagnostic.regimes.${regime}`),
      band && t(`diagnostic.bands.${band}`),
      b2b !== null && t('diagnostic.result.b2bShare', { value: b2b }),
    ]
      .filter(Boolean)
      .join(' · ');
  });

  return { profileLine };
}

export { useProfileLine };

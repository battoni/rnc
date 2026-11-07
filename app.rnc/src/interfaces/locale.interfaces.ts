import type { UiLocale } from '@Types';

interface UiLocaleOption {
  /** The language's own name. Rendered as-is in every UI language — nobody should have to recognise their language spelled in a foreign one. */
  label: string;
  shortLabel: string;
  value: UiLocale;
}

export type { UiLocaleOption };

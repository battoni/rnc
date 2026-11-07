interface Option {
  label: string;
  value: any;
}

interface TipAnchor {
  /** Stable identifier, matched against a tip's `anchor_key`. Also the `tip-key` on `<ATip>`. */
  key: string;
  /** i18n key naming the spot, e.g. "Aba Entradas". */
  labelKey: string;
  /** Route stem to open so the tip can be seen in place (`tips`, not `tips-pt-BR`). */
  routeName?: string;
  /** Query that puts the destination on the right tab, e.g. `{ tab: 'engine' }`. */
  routeQuery?: Record<string, string>;
  /** i18n key for the area, e.g. "Catálogo › Detalhe da categoria". */
  sectionKey: string;
}
export type { Option, TipAnchor };

/**
 * Franco Contabilidade's public contact, as its own site's footer gives it (rnc-1 facts.md §1).
 *
 * A constant in F1 only because the public layout needs it before any endpoint exists: F2 adds
 * `GET /api/public/firm`, which serves the same fields from the `firms` row, and this file goes.
 */
const FIRM_PUBLIC_CONTACT = {
  address: 'Av. Corifeu de Azevedo Marques, 2333, 1º e 2º andares, Butantã, São Paulo-SP',
  mailbox: 'saf@franco-rnc.com.br',
  name: 'Franco Contabilidade',
  phone: '11 3732-5500',
  phoneHref: 'tel:+551137325500',
} as const;

export { FIRM_PUBLIC_CONTACT };

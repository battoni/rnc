const REAL_FORMATTER = new Intl.NumberFormat('pt-BR', { currency: 'BRL', style: 'currency' });

/**
 * Integer cents as Brazilian reais — `123456` → "R$ 1.234,56". Takes cents, never a float:
 * the arithmetic happens in cents and only this speaks it to a person.
 */
function formatReal(cents: number): string {
  return REAL_FORMATTER.format(cents / 100);
}

export { formatReal };

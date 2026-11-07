/**
 * Money is an integer of cents everywhere past the input that produced it — the API sends and
 * stores `*_cents`. This converts the one float that arrives (`InputNumber mode="currency"`)
 * back into cents; formatting for a person belongs to a locale (`formatReal`).
 */
function toCents(value: number): number {
  return Math.round(value * 100);
}

export { toCents };

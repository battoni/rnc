/**
 * Ids are integers on the wire — every API resource sends them as numbers. A route param or a query
 * value is always a string, so a screen converts it HERE, once, at the boundary, and compares
 * numbers with numbers from then on. A value that is not a positive integer names nothing and comes
 * back as `NaN`, which matches no row and makes the API answer "not found" — the same outcome the
 * raw string produced.
 */
function toRouteId(value: unknown): number {
  const RAW = Array.isArray(value) ? value[0] : value;
  const ID = typeof RAW === 'string' && RAW.trim() !== '' ? Number(RAW) : Number.NaN;

  return Number.isSafeInteger(ID) && ID > 0 ? ID : Number.NaN;
}

export { toRouteId };

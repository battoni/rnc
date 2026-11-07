const NATIONAL_MIN_DIGITS = 10; // area code (2) + 8-digit landline

const NATIONAL_MAX_DIGITS = 11; // area code (2) + 9-digit mobile

/**
 * A Brazilian phone with its area code, masked or not. Length only: whether the line exists is not
 * something a form can know, and the API stores the number with the country code prefixed.
 */
function isValidBrazilPhone(value: string): boolean {
  const DIGITS = value.replace(/\D/g, '');

  return DIGITS.length >= NATIONAL_MIN_DIGITS && DIGITS.length <= NATIONAL_MAX_DIGITS;
}

/**
 * The number the API stored (`5511999998888`, country code first) as a person reads it:
 * `+55 (11) 99999-8888`. Anything that is not a Brazilian number is shown as `+digits` rather
 * than forced into the wrong shape.
 */
function formatBrazilPhone(value: string): string {
  const DIGITS = value.replace(/\D/g, '');
  const MATCH = DIGITS.match(/^55(\d{2})(\d{4,5})(\d{4})$/);

  if (!MATCH) return `+${DIGITS}`;

  const [, AREA_CODE, FIRST_PART, LAST_PART] = MATCH;

  return `+55 (${AREA_CODE}) ${FIRST_PART}-${LAST_PART}`;
}

export { formatBrazilPhone, isValidBrazilPhone };

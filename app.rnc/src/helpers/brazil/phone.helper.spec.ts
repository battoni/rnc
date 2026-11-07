import { describe, expect, it } from 'vitest';
import { formatBrazilPhone, isValidBrazilPhone } from './phone.helper';

describe('isValidBrazilPhone', () => {
  it('accepts a mobile and a landline with the area code, masked or not', () => {
    expect(isValidBrazilPhone('(11) 99999-8888')).toBe(true);
    expect(isValidBrazilPhone('11999998888')).toBe(true);
    expect(isValidBrazilPhone('(11) 3333-4444')).toBe(true);
  });

  it('refuses a number without the area code, or with too many digits', () => {
    expect(isValidBrazilPhone('99999-8888')).toBe(false);
    expect(isValidBrazilPhone('5511999998888')).toBe(false);
    expect(isValidBrazilPhone('')).toBe(false);
  });
});

describe('formatBrazilPhone', () => {
  it('reads the stored number back as a person writes it, mobile and landline', () => {
    expect(formatBrazilPhone('5511999998888')).toBe('+55 (11) 99999-8888');
    expect(formatBrazilPhone('551133334444')).toBe('+55 (11) 3333-4444');
  });

  it('does not force a foreign number into the Brazilian shape', () => {
    expect(formatBrazilPhone('14155550100')).toBe('+14155550100');
  });
});
